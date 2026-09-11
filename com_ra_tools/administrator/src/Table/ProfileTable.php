<?php

/**
 * @package     com_ra_tools.Administrator
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_tools\Administrator\Table;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Authoritative Joomla table class for #__ra_profiles.
 */
class ProfileTable extends Table {

    protected $_supportNullValue = true;

    public function __construct(DatabaseDriver $db) {
        parent::__construct('#__ra_profiles', 'member_id', $db);
        $this->setColumnAlias('published', 'state');
    }

    public function bind($src, $ignore = '') {
        $data = is_object($src) ? get_object_vars($src) : $src;

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Profile data must be an array or object.');
        }

        foreach ([
            'membershipNo',
            'memberRef',
            'contactId',
            'preferred_name',
            'friendlyName',
            'sourceContractVersion',
            'membershipType',
            'jointWith',
            'affiliateMemberPrimaryGroup',
        ] as $nullableTextField) {
            if (array_key_exists($nullableTextField, $data)) {
                $value = trim((string) $data[$nullableTextField]);
                $data[$nullableTextField] = $value === '' ? null : $value;
            }
        }

        foreach (['sourcePayload', 'insightPayload'] as $payloadField) {
            if (!array_key_exists($payloadField, $data)) {
                continue;
            }

            if (is_array($data[$payloadField]) || is_object($data[$payloadField])) {
                $data[$payloadField] = json_encode($data[$payloadField], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $payload = trim((string) $data[$payloadField]);
            $data[$payloadField] = $payload === '' ? null : $payload;

            if ($data[$payloadField] !== null) {
                json_decode($data[$payloadField]);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \InvalidArgumentException('Profile ' . $payloadField . ' must contain valid JSON.');
                }
            }
        }

        foreach ([
            'doNotEmail',
            'wellbeingWalker',
            'walkLeader',
            'noCampaigning',
            'noSurveys',
            'canEmailVolunteers',
            'canEmailMembers',
            'canEmailWellbeingWalkers',
            'canViewMemberData',
            'canViewMemberDate',
            'emailConsent',
            'postConsent',
            'phoneConsent',
            'emailConsentWellbeingWalks',
            'noWalkProgram',
            'volunteer',
            'emailMarketingConsent',
            'postDirectMarketing',
            'telephoneDirectMarketing',
        ] as $nullableBooleanField) {
            if (array_key_exists($nullableBooleanField, $data)) {
                $data[$nullableBooleanField] = $this->normaliseNullableBoolean($data[$nullableBooleanField]);
            }
        }

        foreach (['home_group', 'groupCode', 'affiliateMemberPrimaryGroup'] as $groupField) {
            if (array_key_exists($groupField, $data)) {
                $data[$groupField] = strtoupper(trim((string) $data[$groupField]));
            }
        }

        foreach ([
            'membershipExpiry',
            'membershipEndDate',
            'membershipJoinDate',
            'areaJoinedDate',
            'teamRelationshipFrom',
            'emailPermissionLastUpdated',
            'postPermissionLastUpdated',
            'telephonePermissionLastUpdated',
            'welcome_sent_date',
            'emailConsentLastUpdated',
            'postConsentLastUpdated',
            'phoneConsentLastUpdated',
            'sourceRetrievedAt',
            'insightImportedAt',
            'modified',
        ] as $nullableDateField) {
            if (array_key_exists($nullableDateField, $data)
                    && ($data[$nullableDateField] === '' || $data[$nullableDateField] === '0000-00-00')) {
                $data[$nullableDateField] = null;
            }
        }

        return parent::bind($data, $ignore);
    }

    public function check() {
        if (isset($this->memberRef)) {
            $this->memberRef = trim((string) $this->memberRef);

            if ($this->memberRef === '') {
                $this->memberRef = null;
            }
        }

        if (isset($this->home_group)) {
            $this->home_group = strtoupper(trim((string) $this->home_group));

            if ($this->home_group === '' || !preg_match('/^[A-Z0-9]{4}$/', $this->home_group)) {
                $this->setError('Profile home_group must contain four letters or digits.');
                return false;
            }
        }

        return parent::check();
    }

    public function store($updateNulls = true) {
        $now = Factory::getDate()->toSql();
        $actorId = $this->getActorId();

        if ((int) $this->member_id > 0) {
            $this->modified = $now;
            $this->modified_by = $actorId;
        } else {
            if (empty($this->created)) {
                $this->created = $now;
            }

            if (empty($this->created_by)) {
                $this->created_by = $actorId;
            }

            $this->modified = null;
            $this->modified_by = 0;
        }

        return parent::store($updateNulls);
    }

    private function getActorId(): int {
        $app = Factory::getApplication();

        if (method_exists($app, 'getIdentity')) {
            return (int) $app->getIdentity()->id;
        }

        return 0;
    }

    private function normaliseNullableBoolean($value) {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === true || $value === 1 || $value === '1'
                || strtoupper((string) $value) === 'Y' || strtolower((string) $value) === 'true') {
            return 1;
        }

        if ($value === false || $value === 0 || $value === '0'
                || strtoupper((string) $value) === 'N' || strtolower((string) $value) === 'false') {
            return 0;
        }

        throw new \InvalidArgumentException('Profile boolean fields must contain true, false, 1, 0, Y, or N.');
    }
}
