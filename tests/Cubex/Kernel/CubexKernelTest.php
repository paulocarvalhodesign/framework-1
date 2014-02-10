<?php

class CubexKernelTest extends PHPUnit_Framework_TestCase
{
  /**
   * @return \Cubex\Kernel\CubexKernel
   */
  public function getKernel($defaultAction = 'abc')
  {
    $cubex = new \Cubex\Cubex();
    $cubex->prepareCubex();
    $cubex->processConfiguration($cubex->getConfiguration());
    $kernel = $this->getMock('\Cubex\Kernel\CubexKernel', ['defaultAction']);
    $kernel->expects($this->any())->method("defaultAction")->willReturn(
      $defaultAction
    );
    $kernel->setCubex($cubex);
    return $kernel;
  }

  public function testCubexAwareGetNull()
  {
    $this->setExpectedException('RuntimeException');
    $kernel = $this->getMockForAbstractClass('\Cubex\Kernel\CubexKernel');
    $kernel->getCubex();
  }

  public function testGetDefaultAction()
  {
    //Ensure default action is null to avoid conflicts with user projects
    $kernel = $this->getMockForAbstractClass('\Cubex\Kernel\CubexKernel');
    $this->assertNull($kernel->defaultAction());
  }

  public function testCubexAwareSetGet()
  {
    $kernel = $this->getKernel();
    $cubex  = new \Cubex\Cubex();
    $kernel->setCubex($cubex);
    $this->assertSame($kernel->getCubex(), $cubex);
  }

  public function testHandle()
  {
    $request = \Cubex\Http\Request::createFromGlobals();
    $kernel  = $this->getKernel();
    $resp    = $kernel->handle($request, \Cubex\Cubex::MASTER_REQUEST, false);
    $this->assertInstanceOf(
      '\Symfony\Component\HttpFoundation\Response',
      $resp
    );
  }

  public function testHandleInvalidResponse()
  {
    $request = \Cubex\Http\Request::createFromGlobals();
    $kernel  = $this->getKernel(null);
    $resp    = $kernel->handle($request, \Cubex\Cubex::MASTER_REQUEST, true);
    $this->assertInstanceOf(
      '\Symfony\Component\HttpFoundation\Response',
      $resp
    );
  }

  public function testThrowsExceptionWithNoRouter()
  {
    $this->setExpectedException("RuntimeException", "No IRouter located");
    $request = \Cubex\Http\Request::createFromGlobals();
    $kernel  = $this->getKernel();
    $kernel->getCubex()->instance('\Cubex\Routing\IRouter', new stdClass());
    $kernel->handle($request, \Cubex\Cubex::MASTER_REQUEST, false);
  }

  public function testAttemptCallable()
  {
    $kernel = $this->getKernel();
    $this->assertTrue(
      is_callable($kernel->attemptCallable('\Cubex\Routing\Route,getValue'))
    );
    $this->assertTrue(
      is_callable($kernel->attemptCallable('\Cubex\Routing\Route@getValue'))
    );
    $this->assertFalse(
      is_callable($kernel->attemptCallable('\Cubex\Routing\Route@'))
    );
    $this->assertFalse(
      is_callable($kernel->attemptCallable('\Cubex\Routing\Route'))
    );
    $this->assertFalse(
      is_callable($kernel->attemptCallable('randomString'))
    );
  }

  public function testAttemptMethod()
  {
    $kernel = $this->getMockBuilder('\Cubex\Kernel\CubexKernel')
      ->setMethods(
        [
          "ajaxGetUser",
          'postGetUser',
          'getUser',
          'renderIndex',
          'actionHomepage',
          'defaultAction'
        ]
      )
      ->getMock();

    $request = \Cubex\Http\Request::createFromGlobals();

    $this->assertEquals(
      "getUser",
      $kernel->attemptMethod("getUser", $request)
    );

    $this->assertEquals(
      "renderIndex",
      $kernel->attemptMethod("index", $request)
    );

    $this->assertEquals(
      "actionHomepage",
      $kernel->attemptMethod("homepage", $request)
    );

    $this->assertEquals(
      "defaultAction",
      $kernel->attemptMethod("defaultAction", $request)
    );

    $request->setMethod('POST');

    $this->assertEquals(
      "postGetUser",
      $kernel->attemptMethod("getUser", $request)
    );

    $request->headers->set('X-Requested-With', 'XMLHttpRequest');

    $this->assertEquals(
      "ajaxGetUser",
      $kernel->attemptMethod("getUser", $request)
    );

    $this->assertNull($kernel->attemptMethod("getMissingMethod", $request));
  }

  /**
   * @dataProvider urlProvider
   */
  public function testAttemptUrl($route, $expectUrl = null, $expectCode = null)
  {
    $kernel = $this->getKernel();
    $result = $kernel->attemptUrl($route);

    if($expectUrl === null)
    {
      $this->assertNull($result);
    }
    else
    {
      $this->assertEquals($expectUrl, $result['url']);
      if($expectCode !== null)
      {
        $this->assertEquals($expectCode, $result['code']);
      }
    }
  }

  public function urlProvider()
  {
    return [
      ["invalid", null],
      ["invalid/url", null],
      ["#@home", "home", 302],
      ["#@home", "home"],
      ["#@/home", "/home"],
      ["http://google.com", "http://google.com"],
      ["@301!http://google.com", "http://google.com", 301],
      ["@400!http://google.com", "http://google.com", 400],
    ];
  }

  /**
   * @dataProvider handleResponseProvider
   */
  public function testHandleResponse($response, $captured, $expected)
  {
    $kernel = $this->getKernel();
    $final  = $kernel->handleResponse($response, $captured);
    $this->assertInstanceOf('\Cubex\Http\Response', $final);
    $this->assertEquals($expected, $final->getContent());
  }

  public function handleResponseProvider()
  {
    $resp = new \Cubex\Http\Response("construct");
    return [
      ["response", "capture", "response"],
      ["", "capture", "capture"],
      [$resp, "capture", "construct"],
    ];
  }

  /**
   * @dataProvider executeRouteProvider
   */
  public function testExecuteRoute($kernel, $routeData, $expect)
  {
    $route = new \Cubex\Routing\Route();
    $route->createFromRaw($routeData);

    $request = \Cubex\Http\Request::createFromGlobals();
    $type    = \Cubex\Cubex::MASTER_REQUEST;

    $result = $kernel->executeRoute($route, $request, $type, true);

    if($expect instanceof \Symfony\Component\HttpFoundation\RedirectResponse)
    {
      $this->assertInstanceOf(
        '\Symfony\Component\HttpFoundation\RedirectResponse',
        $result
      );
      $this->assertEquals($expect->getTargetUrl(), $result->getTargetUrl());
    }
    else if($expect instanceof \Symfony\Component\HttpFoundation\Response)
    {
      $this->assertInstanceOf(
        '\Symfony\Component\HttpFoundation\Response',
        $result
      );
      $this->assertEquals($expect->getContent(), $result->getContent());
    }
    else
    {
      $this->assertEquals($expect, $result);
    }
  }

  public function executeRouteProvider()
  {
    $cubex    = new \Cubex\Cubex();
    $response = new \Cubex\Http\Response("hey");

    $toString = $this->getMock('stdClass', ['__toString']);
    $toString->expects($this->any())->method("__toString")->willReturn("test");

    $kernel = $this->getMock(
      '\Cubex\Kernel\CubexKernel',
      ['actionIn', 'handle']
    );
    $kernel->expects($this->any())->method("actionIn")->willReturn("method");
    $kernel->expects($this->any())->method("handle")->willReturn($response);
    $kernel->setCubex($cubex);

    $callable = function ()
    {
      return "testCallable";
    };

    return [
      [$kernel, null, null],
      [$kernel, $response, $response],
      [$kernel, $kernel, $response],
      [
        $kernel,
        '\Cubex\Responses\Error404Response',
        new \Cubex\Responses\Error404Response()
      ],
      [$kernel, 'stdClass', null],
      [$kernel, $callable, "testCallable"],
      [
        $kernel,
        'http://google.com',
        new \Symfony\Component\HttpFoundation\RedirectResponse(
          'http://google.com'
        )
      ],
      [$kernel, 'actionIn', new \Cubex\Http\Response("method")],
      [
        $kernel,
        '\Symfony\Component\HttpFoundation\Request,createFromGlobals',
        \Symfony\Component\HttpFoundation\Request::createFromGlobals()
      ],
      [$kernel, $toString, "test"],
    ];
  }
}
