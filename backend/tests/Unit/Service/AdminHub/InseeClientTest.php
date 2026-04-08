<?php

namespace App\Tests\Unit\Service\AdminHub;

use App\Service\AdminHub\InseeClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class InseeClientTest extends TestCase
{
    public function testIsConfiguredReturnsFalseWithoutToken(): void
    {
        $client = new InseeClient(new MockHttpClient(), '');

        $this->assertFalse($client->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithToken(): void
    {
        $client = new InseeClient(new MockHttpClient(), 'test-token');

        $this->assertTrue($client->isConfigured());
    }

    public function testFindBySirenReturnsNullWithoutToken(): void
    {
        $client = new InseeClient(new MockHttpClient(), '');

        $this->assertNull($client->findBySiren('123456789'));
    }

    public function testFindBySirenRejectsInvalidFormat(): void
    {
        $client = new InseeClient(new MockHttpClient(), 'token');

        $this->assertNull($client->findBySiren('123'));
        $this->assertNull($client->findBySiren('abcdefghi'));
        $this->assertNull($client->findBySiren(''));
    }

    public function testFindBySirenReturnsData(): void
    {
        $responseBody = json_encode([
            'uniteLegale' => [
                'siren' => '443061841',
                'nomUniteLegale' => null,
                'prenom1UniteLegale' => null,
                'dateCreationUniteLegale' => '2002-04-01',
                'periodesUniteLegale' => [
                    [
                        'denominationUniteLegale' => 'GOOGLE FRANCE',
                        'activitePrincipaleUniteLegale' => '62.01Z',
                        'etatAdministratifUniteLegale' => 'A',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        $result = $client->findBySiren('443061841');

        $this->assertNotNull($result);
        $this->assertSame('443061841', $result['siren']);
        $this->assertSame('GOOGLE FRANCE', $result['denomination']);
        $this->assertSame('62.01Z', $result['codeNaf']);
        $this->assertSame('A', $result['etatAdministratif']);
    }

    public function testFindBySirenReturnsNullOn404(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 404]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        $result = $client->findBySiren('000000000');

        $this->assertNull($result);
    }

    public function testFindBySiretReturnsNullWithoutToken(): void
    {
        $client = new InseeClient(new MockHttpClient(), '');

        $this->assertNull($client->findBySiret('44306184100047'));
    }

    public function testFindBySiretRejectsInvalidFormat(): void
    {
        $client = new InseeClient(new MockHttpClient(), 'token');

        $this->assertNull($client->findBySiret('123'));
        $this->assertNull($client->findBySiret(''));
    }

    public function testFindBySiretReturnsData(): void
    {
        $responseBody = json_encode([
            'etablissement' => [
                'siret' => '44306184100047',
                'siren' => '443061841',
                'dateCreationEtablissement' => '2002-04-01',
                'periodesEtablissement' => [
                    ['etatAdministratifEtablissement' => 'A'],
                ],
                'adresseEtablissement' => [
                    'numeroVoieEtablissement' => '8',
                    'typeVoieEtablissement' => 'RUE',
                    'libelleVoieEtablissement' => 'DE LONDRES',
                    'codePostalEtablissement' => '75009',
                    'libelleCommuneEtablissement' => 'PARIS',
                ],
                'uniteLegale' => [
                    'denominationUniteLegale' => 'GOOGLE FRANCE',
                    'periodesUniteLegale' => [
                        ['activitePrincipaleUniteLegale' => '62.01Z'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        $result = $client->findBySiret('44306184100047');

        $this->assertNotNull($result);
        $this->assertSame('44306184100047', $result['siret']);
        $this->assertSame('443061841', $result['siren']);
        $this->assertSame('GOOGLE FRANCE', $result['denomination']);
        $this->assertStringContainsString('PARIS', $result['adresse']);
    }

    public function testSearchReturnsEmptyWithoutToken(): void
    {
        $client = new InseeClient(new MockHttpClient(), '');

        $this->assertSame([], $client->search('Google'));
    }

    public function testSearchReturnsEmptyWithEmptyQuery(): void
    {
        $client = new InseeClient(new MockHttpClient(), 'token');

        $this->assertSame([], $client->search(''));
    }

    public function testSearchReturnsResults(): void
    {
        $responseBody = json_encode([
            'unitesLegales' => [
                [
                    'siren' => '443061841',
                    'dateCreationUniteLegale' => '2002-04-01',
                    'periodesUniteLegale' => [
                        [
                            'denominationUniteLegale' => 'GOOGLE FRANCE',
                            'activitePrincipaleUniteLegale' => '62.01Z',
                            'etatAdministratifUniteLegale' => 'A',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        $results = $client->search('GOOGLE');

        $this->assertCount(1, $results);
        $this->assertSame('443061841', $results[0]['siren']);
        $this->assertSame('GOOGLE FRANCE', $results[0]['denomination']);
    }

    public function testFindBySirenHandlesNetworkError(): void
    {
        $mockResponse = new MockResponse('', ['error' => 'Connection timeout']);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        // Ne doit pas lever d'exception
        $result = $client->findBySiren('443061841');

        $this->assertNull($result);
    }

    public function testFindBySirenStripsSpaces(): void
    {
        $mockResponse = new MockResponse('{}', ['http_code' => 404]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new InseeClient($httpClient, 'test-token');

        // Ne doit pas echouer a cause des espaces
        $result = $client->findBySiren('443 061 841');

        $this->assertNull($result);
    }
}
