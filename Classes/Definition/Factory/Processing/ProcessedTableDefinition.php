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

namespace TYPO3\CMS\ContentBlocks\Definition\Factory\Processing;

use TYPO3\CMS\ContentBlocks\Definition\ContentType\ContentType;

/**
 * @internal Not part of TYPO3's public API.
 */
final class ProcessedTableDefinition
{
    public array $palettes = [];
    public array $fields = [];
    public ?string $typeField = null;
    public bool $isRootTable = true;
    public ?bool $isAggregateRoot = null;
    public array $raw = [];
    public ?ContentType $contentType = null;
}