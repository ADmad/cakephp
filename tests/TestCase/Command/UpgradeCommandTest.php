<?php
declare(strict_types=1);
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         4.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Command;

use Cake\Core\Configure;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamStructureVisitor;

/**
 * UpgradeCommand test.
 */
class UpgradeCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * pluginPaths
     *
     * @var array
     */
    protected $pluginPaths = [];

    /**
     * Namespace
     *
     * @var string
     */
    protected $namespace = '';

    /**
     * setup method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->useCommandRunner(true);

        $ds = [
            'src' => [
                'Template' => [
                    'Email' => ['html' => ['def.php' => '']],
                    'Element' => ['foo.ctp' => ''],
                    'Layout' => ['default.ctp' => ''],
                    'Cell' => [
                        'MyCell' => ['display.ctp' => ''],
                    ],
                    'Plugin' => [
                        'TestPlugin' => [
                            'Layout' => ['Email' => ['text.php' => '']],
                            'Element' => ['bar.ctp' => ''],
                            'Posts' => ['index.ctp' => ''],
                        ],
                    ],
                    'Pages' => [
                        'home.ctp' => '',
                    ],
                ],
            ],
            'plugins' => [
                'TestPlugin' => [
                    'src' => [
                        // This is ensure "src/Cell" does not get renamed.
                        'Cell' => [
                            'TestPluginCell.php' => '',
                        ],
                        'Template' => [
                            'Element' => [
                                'foo.ctp' => '',
                            ],
                            'Layout' => [
                                'plugin.ctp' => '',
                                'Email' => ['html.php' => ''],
                            ],
                            'Cell' => [
                                'TestPluginCell' => ['bar.ctp' => ''],
                            ],
                        ],
                    ],
                ],
                'PluginWithoutTemplates' => [
                    'src' => [],
                ],
            ],
        ];

        $this->fs = vfsStream::setup('root', 444, $ds);

        $this->pluginPaths = Configure::read('App.paths.plugins');
        Configure::write('App.paths.plugins', [$this->fs->url() . '/plugins'], true);

        $this->namespace = Configure::read('App.namespace');
        Configure::write('App.namespace', 'TestApp');
    }

    /**
     * tearDown
     *
     * @return void
     */
    public function tearDown()
    {
        Configure::write('App.paths.plugins', $this->pluginPaths);
        Configure::write('App.namespace', $this->namespace);
    }

    /**
     * testExecute
     *
     * @return void
     */
    public function testExecute()
    {
        $this->exec('upgrade templates --path ' . $this->fs->url());

        $ds = [
            'src' => [],
            'templates' => [
                'email' => ['html' => ['def.php' => '']],
                'element' => ['foo.php' => ''],
                'layout' => ['default.php' => ''],
                'cell' => [
                    'MyCell' => ['display.php' => ''],
                ],
                'plugin' => [
                    'TestPlugin' => [
                        'layout' => ['email' => ['text.php' => '']],
                        'element' => ['bar.php' => ''],
                        'Posts' => ['index.php' => ''],
                    ],
                ],
                'Pages' => [
                    'home.php' => '',
                ],
            ],
            'plugins' => [
                'TestPlugin' => [
                    'src' => [
                        // This is ensure "src/Cell" does not get renamed.
                        'Cell' => [
                            'TestPluginCell.php' => '',
                        ],
                    ],
                    'templates' => [
                        'element' => [
                            'foo.php' => '',
                        ],
                        'layout' => [
                            'plugin.php' => '',
                            'email' => ['html.php' => ''],
                        ],
                        'cell' => [
                            'TestPluginCell' => ['bar.php' => ''],
                        ],
                    ],
                ],
                'PluginWithoutTemplates' => [
                    'src' => [],
                ],
            ],
        ];

        $this->assertEquals(
            ['root' => $ds],
            vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure()
        );
    }
}
