<?php namespace NTLMSoap;

use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;
use \Psr\Log\LoggerInterface;

class Client extends \SoapClient
{
	use LoggerAwareTrait;

	private $options = Array();

	/**
	 *
	 * @param String $url The WSDL url
	 * @param Array $data Soap options
	 * @param \Psr\Log\LoggerAwareInterface $logger
	 * @see \SoapClient::__construct()
	 */
	public function __construct($url, $data,LoggerInterface $logger = null)
	{
		if ($logger)
		{
			$this->setLogger($logger);
		}

		$this->options = $data;

		if (empty($data['ntlm_username']) && empty($data['ntlm_password']))
		{
			parent::__construct($url, $data);
		}
		else
		{
			$this->use_ntlm            = true;
			HttpStream\NTLM::$user     = $data['ntlm_username'];
			HttpStream\NTLM::$password = $data['ntlm_password'];

			stream_wrapper_unregister('http');
			if (!stream_wrapper_register('http', '\\NTLMSoap\\HttpStream\\NTLM'))
			{
				throw new Exception("Unable to register HTTP Handler");
			}

			$time_start = microtime(true);
			parent::__construct($url, $data);

			// if(!empty($this->logger) && (($end_time = microtime(true) - $time_start) > 0.1)){
			// 	$this->logger->debug("WSDL Timer", Array("time" => $end_time, "url" => $url));
			// }

			stream_wrapper_restore('http');
		}

	}

	public function __doRequest($request, $location, $action, $version, $one_way = 0)
	{

	    $headers = array(
	        'Method: POST',
	        'Connection: Keep-Alive',
	        'User-Agent: PHP-SOAP-CURL',
	        'Content-Type: text/xml; charset=utf-8',
	        'SOAPAction: "'.$action.'"',
	    );

	    $this->__last_request_headers = $headers;
	    $this->ch = curl_init($location);


	    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($this->ch, CURLOPT_POST, true );
	    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $request);
	    curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
	    curl_setopt($this->ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
	    curl_setopt($this->ch, CURLOPT_USERPWD, $this->options['ntlm_username'].':'.$this->options['ntlm_password']);

	    $response = curl_exec($this->ch);

	    // TODO: Add some real error handling.
	    // If the response if false than there was an error and we should throw
	    // an exception.
	    if ($response === false) {
	        throw new EWS_Exception(
	          'Curl error: ' . curl_error($this->ch),
	          curl_errno($this->ch)
	        );
	    }

	    // we need to strip BOM characters and &#xn; from the response
	    $response = preg_replace('/(\x00\x00\xFE\xFF|\xFF\xFE\x00\x00|\xFE\xFF|\xFF\xFE|\xEF\xBB\xBF|&#x(\d+);)/i', "", $response);

	    return $response;
	}

	/**
	 * Returns last SOAP request headers
	 *
	 * @link http://php.net/manual/en/function.soap-soapclient-getlastrequestheaders.php
	 *
	 * @return string the last soap request headers
	 */
	public function __getLastRequestHeaders()
	{
	    return implode("\n", $this->__last_request_headers) . "\n";
	}

}
