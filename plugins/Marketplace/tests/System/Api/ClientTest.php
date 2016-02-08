<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\System\Api;

use Piwik\Cache;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Api\Client;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\Plugins\Marketplace\Input\PurchaseType;
use Piwik\Tests\Framework\TestCase\SystemTestCase;
use Piwik\Version;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service as TestService;
use Psr\Log\NullLogger;

/**
 * @group Plugins
 * @group Marketplace
 * @group ClientTest
 * @group Client
 */
class ClientTest extends SystemTestCase
{
    private $domain = 'http://plugins.piwik.org';

    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = $this->buildClient();
        $this->getCache()->flushAll();
    }

    public function test_getPluginInfo_existingPluginOnTheMarketplace()
    {
        $plugin = $this->client->getPluginInfo('SecurityInfo');

        $this->assertNotEmpty($plugin);
        $this->assertEquals(array('name','owner','description','homepage','license','createdDateTime','donate','isTheme','keywords','authors','repositoryUrl','lastUpdated','latestVersion','numDownloads','screenshots','activity','featured','isFree','isPaid','isCustomPlugin','versions','isDownloadable',), array_keys($plugin));
        $this->assertSame('SecurityInfo', $plugin['name']);
        $this->assertSame('piwik', $plugin['owner']);
        $this->assertTrue(is_array($plugin['keywords']));
        $this->assertNotEmpty($plugin['authors']);
        $this->assertGreaterThan(1000, $plugin['numDownloads']);
        $this->assertTrue($plugin['isFree']);
        $this->assertFalse($plugin['isPaid']);
        $this->assertFalse($plugin['isCustomPlugin']);
        $this->assertNotEmpty($plugin['versions']);

        $lastVersion = $plugin['versions'][count($plugin['versions']) - 1];
        $this->assertEquals(array('name', 'release', 'requires','readme', 'numDownloads', 'repositoryChangelogUrl', 'readmeHtml', 'download'), array_keys($lastVersion));
        $this->assertNotEmpty($lastVersion['download']);
    }

    /**
     * @expectedException \Piwik\Plugins\Marketplace\Api\Exception
     * @expectedExceptionMessage Requested plugin does not exist.
     */
    public function test_getPluginInfo_shouldThrowException_IfPluginDoesNotExistOnMarketplace()
    {
        $this->client->getPluginInfo('NotExistingPlugIn');
    }

    public function test_getConsumer_shouldReturnNullAndNotThrowException_IfNotAuthorized()
    {
        $this->assertNull($this->client->getConsumer());
    }

    public function test_searchForPlugins_requestAll()
    {
        $plugins = $this->client->searchForPlugins($keywords = '', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_ALL);

        $this->assertGreaterThan(30, count($plugins));

        foreach ($plugins as $plugin) {
            $this->assertNotEmpty($plugin['name']);
            $this->assertFalse($plugin['isTheme']);
        }
    }

    public function test_searchForPlugins_onlyFree()
    {
        $plugins = $this->client->searchForPlugins($keywords = '', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_FREE);

        $this->assertGreaterThan(30, count($plugins));

        foreach ($plugins as $plugin) {
            $this->assertTrue($plugin['isFree']);
            $this->assertFalse($plugin['isPaid']);
            $this->assertFalse($plugin['isTheme']);
        }
    }

    public function test_searchForPlugins_onlyPaid()
    {
        $plugins = $this->client->searchForPlugins($keywords = '', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_PAID);

        $this->assertLessThan(30, count($plugins));

        foreach ($plugins as $plugin) {
            $this->assertFalse($plugin['isFree']);
            $this->assertTrue($plugin['isPaid']);
            $this->assertFalse($plugin['isTheme']);
        }
    }

    public function test_searchForPlugins_withKeyword()
    {
        $plugins = $this->client->searchForPlugins($keywords = 'login', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_ALL);

        $this->assertLessThan(30, count($plugins));

        foreach ($plugins as $plugin) {
            $this->assertContains($keywords, $plugin['keywords']);
        }
    }

    public function test_searchForThemes_requestAll()
    {
        $plugins = $this->client->searchForThemes($keywords = '', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_ALL);

        $this->assertGreaterThan(3, count($plugins));
        $this->assertLessThan(50, count($plugins));

        foreach ($plugins as $plugin) {
            $this->assertNotEmpty($plugin['name']);
            $this->assertTrue($plugin['isTheme']);
        }
    }

    public function test_getDownloadUrl()
    {
        $url = $this->client->getDownloadUrl('SecurityInfo');

        $start = $this->domain . '/api/2.0/plugins/SecurityInfo/download/';
        $end   = '?coreVersion=' . Version::VERSION;

        $this->assertStringStartsWith($start, $url);
        $this->assertStringEndsWith($end, $url);

        $version = str_replace(array($start, $end), '', $url);

        $this->assertNotEmpty($version);
        $this->assertRegExp('/\d+\.\d+\.\d+/', $version);
    }

    public function test_clientResponse_shouldBeCached()
    {
        $id = 'marketplace.api.2.0.plugins.' . md5(http_build_query(array('keywords' => 'login', 'purchase_type' => '', 'query' => '', 'sort' => '')));

        $cache = $this->getCache();
        $this->assertFalse($cache->contains($id));

        $this->client->searchForPlugins($keywords = 'login', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_ALL);

        $this->assertTrue($cache->contains($id));
        $cachedPlugins = $cache->fetch($id);

        $this->assertInternalType('array', $cachedPlugins);
        $this->assertNotEmpty($cachedPlugins);
        $this->assertGreaterThan(30, $cachedPlugins);
    }

    public function test_cachedClientResponse_shouldBeReturned()
    {
        $id = 'marketplace.api.2.0.plugins.' . md5(http_build_query(array('keywords' => 'login', 'purchase_type' => '', 'query' => '', 'sort' => '')));

        $cache = $this->getCache();
        $cache->save($id, array('plugins' => array('foo' => 'bar')));

        $result = $this->client->searchForPlugins($keywords = 'login', $query = '', $sort = '', $purchaseType = PurchaseType::TYPE_ALL);

        $this->assertSame(array('foo' => 'bar'), $result);
    }

    public function test_getInfoOfPluginsHavingUpdate()
    {
        $service = new TestService($this->domain);
        $client = $this->buildClient($service);
        $client->getInfoOfPluginsHavingUpdate(Plugin\Manager::getInstance()->getLoadedPlugins());

        $this->assertSame('plugins/checkUpdates', $service->action);
        $this->assertSame(array('plugins'), array_keys($service->params));

        $plugins = $service->params['plugins'];
        $this->assertInternalType('string', $plugins);
        $this->assertJson($plugins);
        $plugins = json_decode($plugins, true);

        $names = array(
            'AnonymousPiwikUsageMeasurement' => true,
            'CustomAlerts' => true,
            'CustomDimensions' => true,
            'LogViewer' => true,
            'QueuedTracking' => true,
            'TasksTimetable' => true,
            'TreemapVisualization' => true,
            'VisitorGenerator' => true,
            'SecurityInfo' => true,
        );
        foreach ($plugins['plugins'] as $plugin) {
            $this->assertNotEmpty($plugin['version']);
            unset($names[$plugin['name']]);
        }

        $this->assertEmpty($names);
    }

    private function buildClient($service = null)
    {
        if (!isset($service)) {
            $service = new Service($this->domain);
        }

        return new Client($service, $this->getCache(), new NullLogger());
    }

    private function getCache()
    {
        return Cache::getLazyCache();
    }

}
