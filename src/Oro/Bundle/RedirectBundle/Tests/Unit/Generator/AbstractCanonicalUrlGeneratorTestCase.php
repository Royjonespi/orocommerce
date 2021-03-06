<?php

namespace Oro\Bundle\RedirectBundle\Tests\Unit\Generator;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\RedirectBundle\DependencyInjection\Configuration;
use Oro\Bundle\RedirectBundle\Entity\Slug;
use Oro\Bundle\RedirectBundle\Entity\SluggableInterface;
use Oro\Bundle\RedirectBundle\Generator\CanonicalUrlGenerator;
use Oro\Bundle\RedirectBundle\Provider\RoutingInformationProvider;
use Oro\Bundle\WebsiteBundle\Resolver\WebsiteUrlResolver;
use Oro\Component\Testing\Unit\EntityTrait;
use Oro\Component\Website\WebsiteInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractCanonicalUrlGeneratorTestCase extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /**
     * @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $configManager;

    /**
     * @var Cache|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $cache;

    /**
     * @var RequestStack|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $requestStack;

    /**
     * @var RoutingInformationProvider|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $routingInformationProvider;

    /**
     * @var WebsiteUrlResolver|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $websiteUrlResolver;

    /**
     * @var CanonicalUrlGenerator
     */
    protected $canonicalUrlGenerator;

    protected function setUp(): void
    {
        $this->configManager = $this->createMock(ConfigManager::class);
        $this->cache = $this->createMock(Cache::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->routingInformationProvider = $this->createMock(RoutingInformationProvider::class);
        $this->websiteUrlResolver = $this->createMock(WebsiteUrlResolver::class);
        $this->canonicalUrlGenerator = $this->createGenerator();
    }

    /**
     * @return CanonicalUrlGenerator
     */
    abstract protected function createGenerator(): CanonicalUrlGenerator;

    /**
     * @param string $urlSecurityType
     * @param WebsiteInterface|null $website
     */
    protected function assertUrlTypeCalls(string $urlSecurityType, WebsiteInterface $website = null): void
    {
        $urlTypeKey = 'oro_redirect.canonical_url_type';
        $urlSecurityTypeKey = 'oro_redirect.canonical_url_security_type';
        if ($website) {
            $urlTypeKey .= '.' . $website->getId();
            $urlSecurityTypeKey .= '.' . $website->getId();
        }

        $this->cache->expects($this->any())
            ->method('fetch')
            ->willReturnMap([
                [$urlTypeKey, Configuration::DIRECT_URL],
                [$urlSecurityTypeKey, $urlSecurityType]
            ]);

        $this->configManager->expects($this->any())
            ->method('get')
            ->willReturnMap([
                [$urlTypeKey, false, false, $website, Configuration::DIRECT_URL],
                [$urlSecurityTypeKey, false, false, $website, $urlSecurityType]
            ]);
    }

    /**
     * @param SluggableInterface $data
     * @param string|null $expectedBaseUrl
     */
    protected function assertRequestCalls(
        SluggableInterface $data,
        ?string $expectedBaseUrl = null
    ): void {
        /** @var Request|\PHPUnit\Framework\MockObject\MockObject $request */
        $request = $this->createMock(Request::class);
        $request->expects($this->any())
            ->method('getBaseUrl')
            ->willReturn($expectedBaseUrl);
        $this->requestStack->expects($this->atMost(1))
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->routingInformationProvider->expects($this->never())
            ->method('getRouteData')
            ->with($data);
    }

    /**
     * @param Slug $slug
     * @return SluggableInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected function getSluggableEntityMock(Slug $slug)
    {
        $slugs = new ArrayCollection([$slug]);

        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $data * */
        $data = $this->createMock(SluggableInterface::class);
        $data->expects($this->any())
            ->method('getSlugs')
            ->willReturn($slugs);
        $data->expects($this->any())
            ->method('getBaseSlug')
            ->willReturn($slug);

        $data->expects($this->any())
            ->method('getSlugByLocalization')
            ->willReturn($slug);

        return $data;
    }
}
