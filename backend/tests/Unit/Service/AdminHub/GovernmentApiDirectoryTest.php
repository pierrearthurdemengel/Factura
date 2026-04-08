<?php

namespace App\Tests\Unit\Service\AdminHub;

use App\Service\AdminHub\GovernmentApiDirectory;
use PHPUnit\Framework\TestCase;

class GovernmentApiDirectoryTest extends TestCase
{
    private GovernmentApiDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new GovernmentApiDirectory();
    }

    public function testGetServicesReturnsNonEmptyList(): void
    {
        $services = $this->directory->getServices();

        $this->assertNotEmpty($services);
        $this->assertGreaterThanOrEqual(5, count($services));
    }

    public function testEachServiceHasRequiredFields(): void
    {
        $requiredFields = ['id', 'name', 'description', 'url', 'status', 'category'];

        foreach ($this->directory->getServices() as $service) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $service, "Le service {$service['id']} manque le champ {$field}");
                $this->assertNotEmpty($service[$field], "Le champ {$field} est vide pour {$service['id']}");
            }
        }
    }

    public function testServiceIdsAreUnique(): void
    {
        $ids = array_map(
            static fn (array $s): string => $s['id'],
            $this->directory->getServices(),
        );

        $this->assertSame($ids, array_unique($ids), 'Les IDs de services doivent etre uniques');
    }

    public function testGetServicesByCategoryFiscal(): void
    {
        $services = $this->directory->getServicesByCategory(GovernmentApiDirectory::CATEGORY_FISCAL);

        $this->assertNotEmpty($services);
        foreach ($services as $service) {
            $this->assertSame(GovernmentApiDirectory::CATEGORY_FISCAL, $service['category']);
        }
    }

    public function testGetServicesByCategoryUnknownReturnsEmpty(): void
    {
        $services = $this->directory->getServicesByCategory('unknown_category');

        $this->assertEmpty($services);
    }

    public function testGetIntegratedServicesReturnsOnlyIntegrated(): void
    {
        $services = $this->directory->getIntegratedServices();

        $this->assertNotEmpty($services);
        foreach ($services as $service) {
            $this->assertSame(GovernmentApiDirectory::STATUS_INTEGRATED, $service['status']);
        }
    }

    public function testGetCategoriesReturnsAllCategories(): void
    {
        $categories = $this->directory->getCategories();

        $this->assertArrayHasKey(GovernmentApiDirectory::CATEGORY_FISCAL, $categories);
        $this->assertArrayHasKey(GovernmentApiDirectory::CATEGORY_SOCIAL, $categories);
        $this->assertArrayHasKey(GovernmentApiDirectory::CATEGORY_LEGAL, $categories);
        $this->assertArrayHasKey(GovernmentApiDirectory::CATEGORY_DATA, $categories);

        foreach ($categories as $category) {
            $this->assertArrayHasKey('label', $category);
            $this->assertArrayHasKey('count', $category);
            $this->assertGreaterThanOrEqual(0, $category['count']);
        }
    }

    public function testCategoriesCountMatchesServices(): void
    {
        $categories = $this->directory->getCategories();
        $totalFromCategories = array_sum(array_column($categories, 'count'));
        $totalServices = count($this->directory->getServices());

        $this->assertSame($totalServices, $totalFromCategories);
    }

    public function testUrssafServiceIsIntegrated(): void
    {
        $services = $this->directory->getServices();
        $urssaf = null;

        foreach ($services as $service) {
            if ('urssaf' === $service['id']) {
                $urssaf = $service;
                break;
            }
        }

        $this->assertNotNull($urssaf);
        $this->assertSame(GovernmentApiDirectory::STATUS_INTEGRATED, $urssaf['status']);
        $this->assertSame(GovernmentApiDirectory::CATEGORY_SOCIAL, $urssaf['category']);
    }

    public function testInseeServiceIsIntegrated(): void
    {
        $services = $this->directory->getServices();
        $insee = null;

        foreach ($services as $service) {
            if ('insee_sirene' === $service['id']) {
                $insee = $service;
                break;
            }
        }

        $this->assertNotNull($insee);
        $this->assertSame(GovernmentApiDirectory::STATUS_INTEGRATED, $insee['status']);
    }
}
