<?php
namespace Cubex\Routing;

use Cubex\ICubexAware;

interface IRouter extends ICubexAware
{
  /**
   * Set the object you wish to handle routing for
   *
   * @param IRoutable $subject
   *
   * @return $this
   */
  public function setSubject(IRoutable $subject);

  /**
   * Process the url against the subjects routes
   *
   * @param string $url     url section to parse
   * @param string $fullUrl full request url
   *
   * @return IRoute
   * @throws \RuntimeException When the subject has not been set
   * @throws \Exception When no route can be found
   */
  public function process($url, $fullUrl = null);
}
