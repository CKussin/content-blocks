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

namespace TYPO3\CMS\ContentBlocks\Tests\Functional\ViewHelpers\Asset;

use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

final class ScriptViewHelperTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    protected array $coreExtensionsToLoad = [
//        'content_blocks',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/content_blocks/Tests/Fixtures/Extensions/test_content_blocks_a',
        'typo3conf/ext/content_blocks/Tests/Fixtures/Extensions/test_content_blocks_b',
        'typo3conf/ext/content_blocks',
    ];

    /**
     * @test
     */
    public function sourceStringIsNotHtmlEncodedBeforePassedToAssetCollector(): void
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource('<cb:asset.script name="typo3tests/content-element-b" identifier="test" file="Frontend.js" priority="0"/>');

        (new TemplateView($context))->render();

        $collectedJavaScripts = $this->get(AssetCollector::class)->getJavaScripts();
        self::assertSame('EXT:test_content_blocks_b/ContentBlocks/ContentElements/content-element-b/Assets/Frontend.js', $collectedJavaScripts['test']['source']);
        self::assertSame([], $collectedJavaScripts['test']['attributes']);
    }

    /**
     * @test
     */
    public function booleanAttributesAreProperlyConverted(): void
    {
        $context = $this->get(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource('<cb:asset.script name="typo3tests/content-element-a" identifier="test" file="my.js" async="1" defer="1" nomodule="1" priority="0"/>');

        (new TemplateView($context))->render();

        $collectedJavaScripts = $this->get(AssetCollector::class)->getJavaScripts();
        self::assertSame('EXT:test_content_blocks_a/ContentBlocks/ContentElements/content-element-a/Assets/my.js', $collectedJavaScripts['test']['source']);
        self::assertSame(['async' => 'async', 'defer' => 'defer', 'nomodule' => 'nomodule'], $collectedJavaScripts['test']['attributes']);
    }
}
