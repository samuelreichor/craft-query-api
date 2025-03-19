<?php

namespace samuelreichoer\queryapi\services;

use Craft;

class SchemaService
{

    public function getSchemaComponents(): array
    {
        $queries = [];
        $mutations = [];

        // Sites
        $label = Craft::t('app', 'Sites');
        [$queries[$label], $mutations[$label]] = $this->_siteSchemaComponents();

        // Sections
        $label = Craft::t('app', 'Sections');
        [$queries[$label], $mutations[$label]] = $this->_sectionSchemaComponents();

        // User Groups
        $label = Craft::t('app', 'User Groups');
        [$queries[$label], $mutations[$label]] = $this->_userSchemaComponents();

        // Volumes
        $label = Craft::t('app', 'Volumes');
        [$queries[$label], $mutations[$label]] = $this->_volumeSchemaComponents();

        return [
            'queries' => $queries,
            'mutations' => $mutations,
        ];
    }

    /**
     * Return site schema components.
     *
     * @return array
     */
    private function _siteSchemaComponents(): array
    {
        $sites = Craft::$app->getSites()->getAllSites(true);
        $queryComponents = [];

        foreach ($sites as $site) {
            $queryComponents["sites.{$site->uid}:read"] = [
                'label' => Craft::t('app', 'Query for elements in the “{site}” site', [
                    'site' => $site->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _sectionSchemaComponents(): array
    {
        $sections = Craft::$app->entries->getAllSections();
        $queryComponents = [];

        foreach ($sections as $section) {
            $queryComponents["sections.{$section->uid}:read"] = [
                'label' => Craft::t('app', 'Query for elements in the “{section}” section', [
                    'section' => $section->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _userSchemaComponents(): array
    {
        $userGroups = Craft::$app->userGroups->getAllGroups();
        $queryComponents = [];

        foreach ($userGroups as $userGroup) {
            $queryComponents["usergroups.{$userGroup->uid}:read"] = [
                'label' => Craft::t('app', 'Query for users in the “{usergroup}” user group', [
                    'usergroup' => $userGroup->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }

    private function _volumeSchemaComponents(): array
    {
        $volumes = Craft::$app->volumes->getAllVolumes();
        $queryComponents = [];

        foreach ($volumes as $volume) {
            $queryComponents["volumes.{$volume->uid}:read"] = [
                'label' => Craft::t('app', 'Query for assets in the “{volume}” volume', [
                    'volume' => $volume->name,
                ]),
            ];
        }

        return [$queryComponents, []];
    }
}
