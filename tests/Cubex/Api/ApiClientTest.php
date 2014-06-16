<?php

class ApiClientTest extends PHPUnit_Framework_TestCase
{
  public function testGet()
  {
    $json = '{"error":{"message":"","code":200},'
      . '"result":["Tasker","worker"],'
      . '"profile":{"callTime":"2.000","executionTime":"39.000"}}';

    $response = new \GuzzleHttp\Message\Response(
      200,
      [],
      \GuzzleHttp\Stream\Stream::factory($json)
    );
    $adapter  = new \GuzzleHttp\Adapter\MockAdapter();
    $adapter->setResponse($response);
    $guzzler = new \GuzzleHttp\Client(['adapter' => $adapter]);

    $client    = new \Cubex\Api\ApiClient('http://www.test.com', $guzzler);
    $apiResult = $client->get('');
    $this->assertInstanceOf('\Cubex\Api\ApiResult', $apiResult);
    $this->assertEquals(['Tasker', 'worker'], $apiResult->getResult());
  }

  public function testPost()
  {

    $response = function (\GuzzleHttp\Adapter\TransactionInterface $transaction)
    {
      $body = $transaction->getRequest()->getBody();
      if($body instanceof \GuzzleHttp\Post\PostBody)
      {
        $resp          = new stdClass();
        $resp->error   = ['message' => '', 'code' => 200];
        $resp->result  = $body->getFields();
        $resp->profile = ['callTime' => '2', 'executionTime' => 34];
        return new \GuzzleHttp\Message\Response(
          200,
          [],
          \GuzzleHttp\Stream\Stream::factory(json_encode($resp))
        );
      }
    };

    $adapter = new \GuzzleHttp\Adapter\MockAdapter();
    $adapter->setResponse($response);
    $guzzler = new \GuzzleHttp\Client(['adapter' => $adapter]);

    $client    = new \Cubex\Api\ApiClient('http://www.test.com', $guzzler);
    $apiResult = $client->post('', ['key' => 'value', 'key2' => 'vals']);
    $this->assertInstanceOf('\Cubex\Api\ApiResult', $apiResult);
    $this->assertEquals(
      (object)['key' => 'value', 'key2' => 'vals'],
      $apiResult->getResult()
    );
  }

  public function testGlobalHeaders()
  {

    $response = function (\GuzzleHttp\Adapter\TransactionInterface $transaction)
    {
      $headers       = $transaction->getRequest()->getHeaders();
      $resp          = new stdClass();
      $resp->error   = ['message' => '', 'code' => 200];
      $resp->result  = $headers;
      $resp->profile = ['callTime' => '2', 'executionTime' => 34];
      return new \GuzzleHttp\Message\Response(
        200,
        [],
        \GuzzleHttp\Stream\Stream::factory(json_encode($resp))
      );
    };

    $adapter = new \GuzzleHttp\Adapter\MockAdapter();
    $adapter->setResponse($response);
    $guzzler = new \GuzzleHttp\Client(['adapter' => $adapter]);

    $client = new \Cubex\Api\ApiClient('http://www.test.com', $guzzler);
    $client->addGlobalHeader('header1', 'val');
    $client->addGlobalHeader('head2', 'val2');
    $client->addGlobalHeader('head3', 'val2');
    $client->removeGlobalHeader('head3');
    $apiResult = $client->get('');
    $this->assertInstanceOf('\Cubex\Api\ApiResult', $apiResult);
    $this->assertArrayHasKey('header1', (array)$apiResult->getResult());
    $this->assertArrayHasKey('head2', (array)$apiResult->getResult());
    $this->assertArrayNotHasKey('head3', (array)$apiResult->getResult());
    $this->assertEquals([0 => 'val2'], $apiResult->getResult()->head2);
  }

  public function testBatch()
  {
    $json = '{"error":{"message":"","code":200},'
      . '"result":["Tasker","worker"],'
      . '"profile":{"callTime":"2.000","executionTime":"39.000"}}';

    $response = new \GuzzleHttp\Message\Response(
      200,
      [],
      \GuzzleHttp\Stream\Stream::factory($json)
    );

    $adapter = new \GuzzleHttp\Adapter\MockAdapter();
    $adapter->setResponse($response);
    $parAdapter = new \GuzzleHttp\Adapter\FakeParallelAdapter($adapter);
    $guzzler    = new \GuzzleHttp\Client(['parallel_adapter' => $parAdapter]);

    $client = new \Cubex\Api\ApiClient('http://www.test.com', $guzzler);

    $client->openBatch();
    $apiResult  = $client->get('/');
    $apiResult2 = $client->get('/');

    $this->assertInstanceOf('\Cubex\Api\ApiResult', $apiResult);
    $this->assertInstanceOf('\Cubex\Api\ApiResult', $apiResult2);
    $this->assertNull($apiResult->getResult());
    $this->assertNull($apiResult2->getResult());

    $client->runBatch();

    $this->assertEquals(['Tasker', 'worker'], $apiResult->getResult());
    $this->assertEquals(['Tasker', 'worker'], $apiResult2->getResult());
  }

  public function testBatchOpenClose()
  {
    $client = new \Cubex\Api\ApiClient('');
    $this->assertFalse($client->isBatchOpen());
    $client->openBatch();
    $this->assertTrue($client->isBatchOpen());
    $client->closeBatch();
    $this->assertFalse($client->isBatchOpen());
  }
}
