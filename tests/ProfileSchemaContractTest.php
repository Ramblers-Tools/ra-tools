<?php

$sql = file_get_contents(dirname(__DIR__) . '/com_ra_tools/administrator/sql/install.mysql.utf8.sql');

if (!preg_match('/CREATE TABLE IF NOT EXISTS `#__ra_profiles` \((.*?)\) ENGINE=/s', $sql, $match)) {
    throw new RuntimeException('Unable to find #__ra_profiles in install SQL.');
}

$profileSql = $match[1];
$profileFields = [
    'membershipNo', 'memberRef', 'contactId', 'title', 'firstName', 'lastName',
    'doNotEmail', 'landline', 'mobile', 'friendlyName', 'membershipStatus',
    'memberType', 'membershipJoinDate', 'membershipExpiry', 'membershipEndDate',
    'teamStatus', 'teamRelationshipFrom', 'wellbeingWalker', 'walkLeader',
    'noWalkProgram', 'noCampaigning', 'noSurveys', 'canEmailVolunteers',
    'canEmailMembers', 'canEmailWellbeingWalkers', 'canViewMemberData',
    'canViewMemberDate', 'emailConsent', 'emailConsentLastUpdated', 'postConsent',
    'postConsentLastUpdated', 'phoneConsent', 'phoneConsentLastUpdated',
    'emailConsentWellbeingWalks',
];

foreach ($profileFields as $sourceField) {
    if (strpos($profileSql, '`' . $sourceField . '`') === false) {
        throw new RuntimeException($sourceField . ' has no matching profile column.');
    }
}

$insightFields = [
    'groupName', 'membershipNo', 'memberType', 'memberTerm', 'membershipStatus',
    'membershipType', 'jointWith', 'title', 'initials', 'firstName', 'lastName',
    'address1', 'address2', 'address3', 'town', 'county', 'country', 'postcode',
    'landline', 'mobile', 'membershipExpiry', 'membershipJoinDate', 'areaName',
    'areaJoinedDate', 'groupCode', 'teamRelationshipFrom', 'volunteer',
    'emailMarketingConsent', 'emailPermissionLastUpdated', 'postDirectMarketing',
    'postPermissionLastUpdated', 'telephoneDirectMarketing',
    'telephonePermissionLastUpdated', 'noWalkProgram',
    'affiliateMemberPrimaryGroup',
];

foreach ($insightFields as $sourceField) {
    if (strpos($profileSql, '`' . $sourceField . '`') === false) {
        throw new RuntimeException($sourceField . ' has no matching Insight profile column.');
    }
}

foreach ([
    'sourcePayload', 'sourceContractVersion', 'sourceRetrievedAt',
    'insightPayload', 'insightImportedAt',
] as $snapshotColumn) {
    if (strpos($profileSql, '`' . $snapshotColumn . '`') === false) {
        throw new RuntimeException('Missing source snapshot column ' . $snapshotColumn . '.');
    }
}

echo count($profileFields) . " supporter fields have matching profile columns; email and roles are excluded\n";
echo count($insightFields) . " Insight fields have matching profile columns; email and sorting surname are excluded\n";
