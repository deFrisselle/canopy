<?php

namespace phpws2\Http;

/**
 * Description
 * @author Jeff Tickle <jtickle at tux dot appstate dot edu>
 */
abstract class ErrorResponse extends \Canopy\Response
{

    protected $request;
    protected $backtrace;
    protected $exception;

    public function __construct(\Canopy\Request $request = null, \Exception $previous = null)
    {
        if (is_null($request)) {
            $request = \Canopy\Server::getCurrentRequest();
        }

        parent::__construct(null, $this->getHttpResponseCode());

        $this->request = $request;
        $this->code = $this->getHttpResponseCode();
        $this->backtrace = debug_backtrace();
        $this->exception = $previous;
    }

    protected abstract function getHttpResponseCode();

    public function getView()
    {
        if (is_null($this->view)) {
            $this->view = $this->createErrorView($this->request, $this);
        }

        return $this->view;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getBacktrace()
    {
        return $this->backtrace;
    }

    public function getException()
    {
        return $this->exception;
    }

    protected function createErrorView(\Canopy\Request $request, \Canopy\Response $response)
    {
        $iter = $request->getAccept()->getIterator();

        foreach ($iter as $type) {
            if ($type->matches('application/json')) {
                return new \phpws2\View\JsonErrorView($request, $response);
            }
            if ($type->matches('application/xml')) {
                return new \phpws2\View\XmlErrorView($request, $response);
            }
            if ($type->matches('text/html')) {
                return new \phpws2\View\HtmlErrorView($request, $response);
            }
        }
    }

}
