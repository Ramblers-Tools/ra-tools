<?php

/**
 * Keeps the Ramblers Tools app's sign-in codes and tokens in the database. Only SHA-256 hashes
 * of codes and tokens are stored, never the values themselves. See AppSignIn for the rules.
 *
 * Tables: #__ra_app_codes, #__ra_app_tokens and #__ra_app_attempts (install.mysql.utf8.sql).
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class AppSignInStore {

    private $db;

    public function __construct(?DatabaseInterface $db = null) {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /** Makes a one-time code for a member who has allowed the app. Returns the code. */
    public function createCode(int $userId, string $clientId, string $challenge): string {
        $this->removeOldCodes();
        $code = AppSignIn::newSecret();
        $row = (object) [
                    'code_hash' => AppSignIn::hash($code),
                    'user_id' => $userId,
                    'code_challenge' => $challenge,
                    'redirect_uri' => AppSignIn::REDIRECT_URI,
                    'client_id' => $clientId,
                    'created' => self::now(),
                    'expires' => gmdate('Y-m-d H:i:s', time() + AppSignIn::CODE_SECONDS),
        ];
        $this->db->insertObject('#__ra_app_codes', $row);
        return $code;
    }

    /** Finds a code and deletes it, so it can only be used once. Null if there's no such code. */
    public function takeCode(string $code): ?array {
        $hash = AppSignIn::hash($code);
        $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ra_app_codes'))
                ->where($this->db->quoteName('code_hash') . ' = :hash')
                ->bind(':hash', $hash);
        $row = $this->db->setQuery($query)->loadAssoc();
        if (!$row) {
            return null;
        }
        $delete = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ra_app_codes'))
                ->where($this->db->quoteName('code_hash') . ' = :hash')
                ->bind(':hash', $hash);
        $this->db->setQuery($delete)->execute();
        // If another request took it at the same moment, only one of them deleted it.
        return $this->db->getAffectedRows() === 1 ? $row : null;
    }

    private function removeOldCodes(): void {
        $now = self::now();
        $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ra_app_codes'))
                ->where($this->db->quoteName('expires') . ' < :now')
                ->bind(':now', $now);
        $this->db->setQuery($query)->execute();
    }

    /** Makes a token for a member. Returns the token. */
    public function createToken(int $userId, string $clientId, string $userAgent): string {
        $token = AppSignIn::newSecret();
        $row = (object) [
                    'token_hash' => AppSignIn::hash($token),
                    'user_id' => $userId,
                    'client_id' => $clientId,
                    'user_agent' => mb_substr($userAgent, 0, 255),
                    'created' => self::now(),
                    'last_used' => self::now(),
        ];
        $this->db->insertObject('#__ra_app_tokens', $row);
        return $token;
    }

    /**
     * The member a token belongs to, if the token still works and the member isn't blocked.
     * Notes when it was last used.
     */
    public function userForToken(string $token): ?int {
        if ($token === '') {
            return null;
        }
        $hash = AppSignIn::hash($token);
        $query = $this->db->getQuery(true)
                ->select(['t.id', 't.user_id', 't.created', 't.last_used', 't.revoked', 'u.block'])
                ->from($this->db->quoteName('#__ra_app_tokens', 't'))
                ->join('INNER', $this->db->quoteName('#__users', 'u'), 'u.id = t.user_id')
                ->where('t.token_hash = :hash')
                ->bind(':hash', $hash);
        $row = $this->db->setQuery($query)->loadAssoc();
        if (!$row || (int) $row['block'] !== 0 || !AppSignIn::tokenUsable($row, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))) {
            return null;
        }
        $now = self::now();
        $id = (int) $row['id'];
        $update = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ra_app_tokens'))
                ->set($this->db->quoteName('last_used') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':now', $now)
                ->bind(':id', $id, ParameterType::INTEGER);
        $this->db->setQuery($update)->execute();
        return (int) $row['user_id'];
    }

    /** Revokes one token, by its value (the app signing out). Returns the member, if any. */
    public function revokeToken(string $token): ?int {
        $hash = AppSignIn::hash($token);
        $query = $this->db->getQuery(true)
                ->select(['id', 'user_id'])
                ->from($this->db->quoteName('#__ra_app_tokens'))
                ->where($this->db->quoteName('token_hash') . ' = :hash')
                ->where($this->db->quoteName('revoked') . ' IS NULL')
                ->bind(':hash', $hash);
        $row = $this->db->setQuery($query)->loadAssoc();
        if (!$row) {
            return null;
        }
        $this->revoke((int) $row['user_id'], (int) $row['id']);
        return (int) $row['user_id'];
    }

    /** Revokes one of a member's sign-ins, or all of them when $id is null. */
    public function revoke(int $userId, ?int $id = null): int {
        $now = self::now();
        $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__ra_app_tokens'))
                ->set($this->db->quoteName('revoked') . ' = :now')
                ->where($this->db->quoteName('user_id') . ' = :user')
                ->where($this->db->quoteName('revoked') . ' IS NULL')
                ->bind(':now', $now)
                ->bind(':user', $userId, ParameterType::INTEGER);
        if ($id !== null) {
            $query->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        }
        $this->db->setQuery($query)->execute();
        return $this->db->getAffectedRows();
    }

    /** A member's sign-ins that still work, newest first, for their list on the site. */
    public function signIns(int $userId): array {
        $query = $this->db->getQuery(true)
                ->select(['id', 'client_id', 'user_agent', 'created', 'last_used', 'revoked'])
                ->from($this->db->quoteName('#__ra_app_tokens'))
                ->where($this->db->quoteName('user_id') . ' = :user')
                ->where($this->db->quoteName('revoked') . ' IS NULL')
                ->order($this->db->quoteName('created') . ' DESC')
                ->bind(':user', $userId, ParameterType::INTEGER);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return array_values(array_filter(
                        $this->db->setQuery($query)->loadAssocList(),
                        fn($row) => AppSignIn::tokenUsable($row, $now)
        ));
    }

    /**
     * Counts a try at the token endpoint from this address. Returns false once there have been
     * too many in the last minute. The address is stored hashed.
     */
    public function allowTry(string $ip): bool {
        $hash = AppSignIn::hash('ra-app|' . $ip);
        $since = gmdate('Y-m-d H:i:s', time() - 60);
        $old = gmdate('Y-m-d H:i:s', time() - 3600);
        $cleanup = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__ra_app_attempts'))
                ->where($this->db->quoteName('created') . ' < :old')
                ->bind(':old', $old);
        $this->db->setQuery($cleanup)->execute();

        $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__ra_app_attempts'))
                ->where($this->db->quoteName('ip_hash') . ' = :hash')
                ->where($this->db->quoteName('created') . ' >= :since')
                ->bind(':hash', $hash)
                ->bind(':since', $since);
        if ((int) $this->db->setQuery($query)->loadResult() >= AppSignIn::TRIES_PER_MINUTE) {
            return false;
        }
        // insertObject takes the row by reference, so it has to be a variable.
        $row = (object) ['ip_hash' => $hash, 'created' => self::now()];
        $this->db->insertObject('#__ra_app_attempts', $row);
        return true;
    }

    /** Notes a sign-in or sign-out in #__ra_logfile. Never with a code or token. */
    public function log(string $what, int $userId, string $message): void {
        try {
            $row = (object) [
                        'sub_system' => 'RA App',
                        'record_type' => mb_substr($what, 0, 2),
                        'ref' => (string) $userId,
                        'message' => mb_substr($message, 0, 250),
            ];
            $this->db->insertObject('#__ra_logfile', $row);
        } catch (\Throwable $e) {
            // Logging must never stop a sign-in.
        }
    }
}
