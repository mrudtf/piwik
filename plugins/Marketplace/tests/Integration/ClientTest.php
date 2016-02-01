<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\System\Api;

use Piwik\Cache;
use Piwik\Filesystem;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Api\Client;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service as TestService;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Client as ClientBuilder;
use Piwik\Version;

/**
 * @group Plugins
 * @group Marketplace
 * @group ClientTest
 * @group Client
 */
class ClientTest extends IntegrationTestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var TestService
     */
    private $service;

    public function setUp()
    {
        parent::setUp();

        $this->service = new TestService();
        $this->client = $this->buildClient();
    }

    public function test_download()
    {
        $this->service->returnFixture('v2.0_plugins_TreemapVisualization_info.json');

        $file = $this->client->download('AnyPluginName');

        $this->assertFileExists($file);
        $this->assertStringEqualsFile($file, 'http://plugins.piwik.org/api/2.0/plugins/TreemapVisualization/download/1.0.1?coreVersion=' . Version::VERSION);
        Filesystem::deleteFileIfExists($file);

        $this->assertStringStartsWith(PIWIK_INCLUDE_PATH . '/tmp/latest/plugins/', $file);
        $this->assertStringEndsWith('.zip', $file);
    }

    /**
     * @expectedException \Piwik\Plugins\Marketplace\Api\Exception
     * @expectedExceptionMessage Requested plugin does not exist.
     */
    public function test_getPluginInfo_shouldThrowException_IfNotAllowedToRequestPlugin()
    {
        $this->service->returnFixture('v2.0_plugins_CustomPlugin1_info-access_token-not_existing_token.json');
        $this->client->getPluginInfo('CustomPlugin1');
    }

    private function buildClient()
    {
        return ClientBuilder::build($this->service);
    }

}
