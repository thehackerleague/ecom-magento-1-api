<?php

class Thl_Api_Api_Middleware_Quote
{
	public function handle($request, $next)
	{	
		$token = $this->getToken($request);

		if(!$token) {
			throw new \League\Route\Http\Exception(400,"No Quote has been detected.");
		}

		$token = Mage::helper('api3/auth')->decode($token);

		$payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;
        if (!$quoteId) {
            throw new \League\Route\Http\Exception(400,"No Quote has been detected.");
        }
		
		return $next($request);
	}

	/**
     * Get the JWT from the request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function getToken($request)
    {
        try {
            $token = $this->parseAuthorizationHeader($request);
        } catch (Exception $exception) {
            if (! $token = $request->get('token', false)) {
                throw $exception;
            }
        }

        return $token;
    }

    /**
     * Parse JWT from the authorization header.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    protected function parseAuthorizationHeader($request)
    {
        return trim(str_ireplace($this->getAuthorizationMethod(), '', $request->header('authorization')));
    }

    /**
     * Get the providers authorization method.
     *
     * @return string
     */
    public function getAuthorizationMethod()
    {
        return 'bearer';
    }
}