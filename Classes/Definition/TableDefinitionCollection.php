<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace TYPO3\CMS\ContentBlocks\Definition;

use TYPO3\CMS\ContentBlocks\Service\ConfigurationService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TableDefinitionCollection implements \IteratorAggregate, SingletonInterface
{
    /**
     * @var TableDefinition[]
     */
    private array $definitions = [];

    public function __clone()
    {
        $this->definitions = array_map(function (TableDefinition $tableDefinition) {
            return clone $tableDefinition;
        }, $this->definitions);
    }

    public function addTable(TableDefinition $tableDefinition): void
    {
        if (!$this->hasTable($tableDefinition->getTable())) {
            $this->definitions[$tableDefinition->getTable()] = $tableDefinition;
        }
    }

    public function getTable(string $table): TableDefinition
    {
        if ($this->hasTable($table)) {
            return $this->definitions[$table];
        }
        throw new \OutOfBoundsException('The table "' . $table . '" does not exist.', 1628925803);
    }

    public function hasTable(string $table): bool
    {
        return isset($this->definitions[$table]);
    }

    public function toArray(): array
    {
        $tablesArray = array_merge([], ...$this->getTablesAsArray());
        return [
            'tables' => $tablesArray,
        ];
    }

    public function getTablesAsArray(): iterable
    {
        foreach ($this->definitions as $definition) {
            yield [$definition->getTable() => $definition->toArray()];
        }
    }

    public static function createFromArray(array $contentBlocksList): TableDefinitionCollection
    {
        $tableDefinitionCollection = new self();

        /** @var ConfigurationService $configurationService */
        $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);

        $tableDefinition = [];

        // Since we need to sum up all lvl 0 ContentBlock fields to tt_content,
        // we have to handle the tt_content table a bit different from collection tables.
        foreach ($contentBlocksList as $contentBlock) {
            // basic ContentBlock / CType data
            $cType = str_replace('/', '-', $contentBlock['composerJson']['name']);
            $collectionTablePrefix = $configurationService->getDatabaseCollectionTablePrefix() . $cType; // needed for unique collection table names later
            $ttContentColumnPrefix = $configurationService->getDatabaseTtContentPrefix() . $cType; // needed for unique column names in tt_content later

            // collect data for tt_content from each ContentBlock
            if (isset($contentBlock['yaml']['fields']) && count($contentBlock['yaml']['fields']) > 0) {
                foreach ($contentBlock['yaml']['fields'] as $ttContentField) {

                    // unique tt_content column name
                    $currentColumnName = $ttContentColumnPrefix . '_' . $ttContentField['identifier'];

                    $ttContentField = $tableDefinitionCollection->processCollections(
                        $ttContentField,
                        'tt_content',
                        $currentColumnName,
                        $collectionTablePrefix
                    );

                    // add to tt_content fields
                    $tableDefinition['fields'][$currentColumnName] = [
                        'identifier' => $currentColumnName,
                        'config' => $ttContentField,
                    ];
                }
            }

            // elements for TypeDefinition
            [$vendor, $package] = explode('/', $contentBlock['composerJson']['name']);
            $path = $configurationService->getBasePath() . $package . DIRECTORY_SEPARATOR;

            $tableDefinition['elements'][] = [
                'composerName' => $contentBlock['composerJson']['name'],
                'identifier' => $contentBlock['composerJson']['name'],
                'columns' => array_keys($tableDefinition['fields']),
                'vendor' => $vendor,
                'package' => $package,
                'publicPath' => $path . $configurationService->getContentBlocksPublicPath() . DIRECTORY_SEPARATOR,
                'privatePath' => $path . $configurationService->getContentBlocksPrivatePath() . DIRECTORY_SEPARATOR,
                'wizardGroup' => ($contentBlock['yaml']['group'] ?? ''),
                'icon' => $contentBlock['icon'],
                'iconProvider' => $contentBlock['iconProvider'],
            ];

        }
        // add tt_content definition
        $tableDefinitionCollection->addTable(
            TableDefinition::createFromTableArray(
                'tt_content',
                $tableDefinition,
            )
        );
        return $tableDefinitionCollection;
    }

    /**
     * reduce redundant code by using this method.
     */
    public function processCollections(array $field, string $table, string $currentColumnName, string $collectionTablePrefix = ''): array
    {
        // take care of collections
        if (
            $field['type'] === 'Collection'
            && isset($field['properties']['fields'])
            && count($field['properties']['fields']) > 0
        ) {
            // unique collection table name
            $collectionTableName = (($collectionTablePrefix !== '') ? $collectionTablePrefix . '_' : '') . $table . '_' . $field['identifier'];

            // enrich infos for inline relations
            // enrich infos for inline relations
            $field['properties']['foreign_table'] = $collectionTableName; // The table name of the child records
            $field['properties']['foreign_field'] = $currentColumnName; // the field of the child record pointing to the parent record. This defines where to store the uid of the parent record.
            $field['properties']['foreign_table_field'] = $table; // the field of the child record pointing to the parent record. This defines where to store the table name of the parent record.
            $field['properties']['foreign_match_fields'] = [
                $table => $currentColumnName
            ]; // Array of field-value pairs to both insert and match against when writing/reading IRRE relations.

            // add collection table to collection
            $this->createCollectionTables(
                $collectionTableName,
                $field['properties']['fields']
            );

        }
        return $field;
    }

    /**
     * Create tables recursivly.
     *
     * @param string $table     -> Current table to process
     * @param array $fieldsList -> the list of fields / columns for this table
     * @param string $collectionTablePrefix -> recursivly enhanced, but for lvl 1 its only "cb_"
     */
    public function createCollectionTables(
        string $table,
        array $fieldsList,
        string $collectionTablePrefix = ''
    ) {
        $tableDefinition = [];

        // collect data for tt_content from each ContentBlock
        if (count($fieldsList) > 0) {
            foreach ($fieldsList as $field) {
                // add to field to table
                $tableDefinition['fields'][$field['identifier']] = [
                    'identifier' => $field['identifier'], // currentColumnName
                    'config' => $this->processCollections(
                        $field,
                        $table,
                        $field['identifier'], // currentColumnName
                        $collectionTablePrefix
                    ),
                ];
            }
        }

        $this->addTable(
            TableDefinition::createFromTableArray(
                $table,
                $tableDefinition,
            )
        );
    }

    /**
     * @return \Traversable|TableDefinition[]
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->definitions);
    }
}