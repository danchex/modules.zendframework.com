<?php

namespace ApplicationTest\Service;

use Application\Service\RepositoryRetriever;
use EdpGithub;
use EdpGithub\Api;
use EdpGithub\Collection;
use EdpGithub\Listener\Exception;
use PHPUnit_Framework_TestCase;

class RepositoryRetrieverTest extends PHPUnit_Framework_TestCase
{
    public function testCanRetrieveUserRepositories()
    {
        $payload = [
            ['name' => 'foo'],
            ['name' => 'bar'],
            ['name' => 'baz'],
        ];

        $client = $this->getClientMock(
            new Api\User(),
            $payload
        );

        $service = new RepositoryRetriever($client);

        $repositories = $service->getUserRepositories('foo');

        $this->assertInstanceOf(Collection\RepositoryCollection::class, $repositories);

        $count = 0;
        foreach ($repositories as $repository) {
            $this->assertEquals(current($payload), (array)$repository);
            next($payload);
            ++$count;
        }

        $this->assertEquals(count($payload), $count);
    }

    public function testCanRetrieveUserRepositoryMetadata()
    {
        $payload = [
            'name' => 'foo',
            'url' => 'http://foo.com',
        ];

        $client = $this->getClientMock(
            new Api\Repos(),
            $payload
        );

        $service = new RepositoryRetriever($client);

        $metadata = $service->getUserRepositoryMetadata('foo', 'bar');

        $this->assertInstanceOf('stdClass', $metadata);
        $this->assertEquals($payload, (array)$metadata);
    }

    public function testCanRetrieveRepositoryFileContent()
    {
        $payload = [
            'content' => base64_encode('foo'),
        ];

        $client = $this->getClientMock(
            new Api\Repos(),
            $payload
        );

        $service = new RepositoryRetriever($client);

        $response = $service->getRepositoryFileContent('foo', 'bar', 'foo.baz');

        $this->assertEquals('foo', $response);
    }

    public function testRepositoryContentCanParsedMarkdown()
    {
        $content = 'repository file __FOO__ content';
        $markdown = function($content) {
            return str_replace('__FOO__', 'bar', $content);
        };

        $apiMock = $this->getMock(Api\Markdown::class, ['content','render']);
        $apiMock
            ->expects($this->once())
            ->method('render')
            ->with($this->equalTo($content))
            ->willReturn($markdown($content));

        $apiMock
            ->expects($this->any())
            ->method('content')
            ->willReturn(json_encode(['content' => base64_encode($content)]));

        $clientMock = $this->getMock(EdpGithub\Client::class);
        $clientMock->expects($this->any())
            ->method('api')
            ->willReturn($apiMock);

        $service = new RepositoryRetriever($clientMock);
        $contentMarkdown = $service->getRepositoryFileContent('foo', 'bar', 'foo.md', true);

        $this->assertEquals('repository file bar content', $contentMarkdown);
    }

    public function testRepositoryContentMarkdownFails()
    {
        $content = 'repository file __FOO__ content';
        $apiMock = $this->getMock(Api\Markdown::class, ['content','render']);
        $apiMock
            ->expects($this->once())
            ->method('render')
            ->willThrowException(new Exception\RuntimeException);

        $apiMock
            ->expects($this->any())
            ->method('content')
            ->willReturn(json_encode(['content' => base64_encode($content)]));

        $clientMock = $this->getMock(EdpGithub\Client::class);
        $clientMock->expects($this->any())
            ->method('api')
            ->willReturn($apiMock);

        $service = new RepositoryRetriever($clientMock);
        $contentMarkdown = $service->getRepositoryFileContent('foo', 'bar', 'foo.md', true);

        $this->assertNull($contentMarkdown);
    }

    public function testResponseContentMissingOnGetRepositoryFileContent()
    {
        $payload = [];

        $client = $this->getClientMock(
            new Api\Repos(),
            $payload
        );

        $service = new RepositoryRetriever($client);
        $response = $service->getRepositoryFileContent('foo', 'bar', 'baz');

        $this->assertFalse($response);
    }

    public function testCanRetrieveRepositoryFileMetadata()
    {
        $payload = [
            'name' => 'foo',
            'url' => 'http://foo.com',
        ];

        $client = $this->getClientMock(
            new Api\Repos(),
            $payload
        );

        $service = new RepositoryRetriever($client);

        $metadata = $service->getRepositoryFileMetadata('foo', 'bar', 'baz');

        $this->assertInstanceOf('stdClass', $metadata);
        $this->assertEquals($payload, (array) $metadata);
    }

    public function testCanRetrieveAuthenticatedUserRepositories()
    {
        $payload = [
            ['name' => 'foo'],
            ['name' => 'bar'],
            ['name' => 'baz'],
        ];

        $client = $this->getClientMock(
            new Api\CurrentUser(),
            $payload
        );

        $service = new RepositoryRetriever($client);

        $repositories = $service->getAuthenticatedUserRepositories();

        $this->assertInstanceOf(Collection\RepositoryCollection::class, $repositories);

        $count = 0;
        foreach ($repositories as $repository) {
            $this->assertEquals(current($payload), (array) $repository);
            next($payload);
            ++$count;
        }

        $this->assertEquals(count($payload), $count);
    }


    public function testRepositoryFileContentFails()
    {
        $clientMock = $this->getMock('EdpGithub\Client');
        $clientMock->expects($this->any())
            ->method('api')
            ->willThrowException(new Exception\RuntimeException);

        $service = new RepositoryRetriever($clientMock);
        $response = $service->getRepositoryFileContent('foo', 'bar', 'baz');
        $this->assertFalse($response);
    }

    public function testRepositoryDoesNotExists()
    {
        $clientMock = $this->getMock('EdpGithub\Client');
        $clientMock->expects($this->any())
            ->method('api')
            ->willThrowException(new Exception\RuntimeException);

        $service = new RepositoryRetriever($clientMock);
        $response = $service->getUserRepositoryMetadata('foo', 'bar');
        $this->assertFalse($response);
    }


    /**
     * @param Api\AbstractApi $apiInstance
     * @param array $payload
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getClientMock(Api\AbstractApi $apiInstance, array $payload = [])
    {
        $response = $this->getMock('Zend\Http\Response');

        $response
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($payload))
        ;

        $headers = $this->getMock('Zend\Http\Headers');

        $response
            ->expects($this->any())
            ->method('getHeaders')
            ->willReturn($headers)
        ;

        $httpClient = $this->getMock('EdpGithub\Http\Client');

        $httpClient
            ->expects($this->any())
            ->method('get')
            ->willReturn($response)
        ;

        $client = $this->getMock('EdpGithub\Client');

        $client
            ->expects($this->any())
            ->method('getHttpClient')
            ->willReturn($httpClient)
        ;

        $apiInstance->setClient($client);

        $client
            ->expects($this->any())
            ->method('api')
            ->willReturn($apiInstance)
        ;

        return $client;
    }
}
