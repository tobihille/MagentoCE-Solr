<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
/**
 * @copyright Copyright 2007 Conduit Internet Technologies, Inc. (http://conduit-it.com)
 * @license Apache Licence, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package Apache
 * @subpackage Solr
 * @author Donovan Jimenez <djimenez@conduit-it.com>
 */

/**
 * Starting point for the Solr API. Represents a Solr server resource and has
 * methods for pinging, adding, deleting, committing, optimizing and searching.
 *
 * Example Usage:
 * <code>
 * ...
 * $solr = new Apache_Solr_Service(); //or explicitly new Apache_Solr_Service('localhost', 8180, '/solr')
 *
 * if ($solr->ping())
 * {
 * 		$solr->deleteByQuery('*:*'); //deletes ALL documents - be careful :)
 *
 * 		$document = new Apache_Solr_Document();
 * 		$document->id = uniqid(); //or something else suitably unique
 *
 * 		$document->title = 'Some Title';
 * 		$document->content = 'Some content for this wonderful document. Blah blah blah.';
 *
 * 		$solr->addDocument($document); 	//if you're going to be adding documents in bulk using addDocuments
 * 										//with an array of documents is faster
 *
 * 		$solr->commit(); //commit to see the deletes and the document
 * 		$solr->optimize(); //merges multiple segments into one
 *
 * 		//and the one we all care about, search!
 * 		//any other common or custom parameters to the request handler can go in the
 * 		//optional 4th array argument.
 * 		$solr->search('content:blah', 0, 10, array('sort' => 'timestamp desc'));
 * }
 * ...
 * </code>
 *
 * @todo Investigate using other HTTP clients other than file_get_contents built-in handler. Could provide performance
 * improvements when dealing with multiple requests by using HTTP's keep alive functionality
 */
class Apache_Solr_Service
{
	/**
	 * Response version we support
	 */
	const SOLR_VERSION = '2.2';

	/**
	 * Response writer we support
	 *
	 * @todo Solr 1.3 release may change this to SerializedPHP or PHP implementation
	 */
	const SOLR_WRITER = 'json';

	/**
	 * Servlet mappings
	 */
	const PING_SERVLET = 'admin/ping';
	const UPDATE_SERVLET = 'update';
	const SEARCH_SERVLET = 'select';
	const THREADS_SERVLET = 'admin/threads';
	const NUMBER_RESEND_REQUEST = 5;

	/**
	 * Server identification strings
	 *
	 * @var string
	 */
	protected $_host, $_port, $_path;

	/**
	 * Query delimiters. Someone might want to be able to change
	 * these (to use &amp; instead of & for example), so I've provided them.
	 *
	 * @var string
	 */
	protected $_queryDelimiter = '?', $_queryStringDelimiter = '&';

	/**
	 * Constructed servlet full path URLs
	 *
	 * @var string
	 */
	protected $_updateUrl, $_searchUrl, $_threadsUrl;

	/**
	 * Keep track of whether our URLs have been constructed
	 *
	 * @var boolean
	 */
	protected $_urlsInited = false;

	/**
	 * Stream context for posting
	 *
	 * @var resource
	 */
	protected $_postContext;

	/**
	 * Escape a value for special query characters such as ':', '(', ')', '*', '?', etc.
	 *
	 * NOTE: inside a phrase fewer characters need escaped, use {@link Apache_Solr_Service::escapePhrase()} instead
	 *
	 * @param string $value
	 * @return string
	 */
	static public function escape($value)
	{
		//list taken from http://lucene.apache.org/java/docs/queryparsersyntax.html#Escaping%20Special%20Characters
		$pattern = '/(\+|-|&&|\|\||!|\(|\)|\{|}|\[|]|\^|"|~|\*|\?|:|\\\)/';
		$replace = '\\\$1';

		return preg_replace($pattern, $replace, $value);
	}

	/**
	 * Escape a value meant to be contained in a phrase for special query characters
	 *
	 * @param string $value
	 * @return string
	 */
	static public function escapePhrase($value)
	{
		$pattern = '/("|\\\)/';
		$replace = '\\\$1';

		return preg_replace($pattern, $replace, $value);
	}

	/**
	 * Convenience function for creating phrase syntax from a value
	 *
	 * @param string $value
	 * @return string
	 */
	static public function phrase($value)
	{
		return '"' . self::escapePhrase($value) . '"';
	}

	/**
	 * Constructor. All parameters are optional and will take on default values
	 * if not specified.
	 *
	 * @param string $host
	 * @param string $port
	 * @param string $path
	 */
	public function __construct($host = 'localhost', $port = 8180, $path = '/solr/')
	{
		$this->setHost($host);
		$this->setPort($port);
		$this->setPath($path);

		$this->_initUrls();

		//set up the stream context for posting with file_get_contents
		$contextOpts = array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: text/xml; charset=UTF-8\r\n" //php.net example showed \r\n at the end
			)
		);

		$this->_postContext = stream_context_create($contextOpts);
	}

	/**
	 * Return a valid http URL given this server's host, port and path and a provided servlet name
	 *
	 * @param string $servlet
	 * @return string
	 */
	protected function _constructUrl($servlet, $params = array())
	{
		if (count($params))
		{
			//escape all parameters appropriately for inclusion in the query string
			$escapedParams = array();

			foreach ($params as $key => $value)
			{
				$escapedParams[] = urlencode($key) . '=' . urlencode($value);
			}

			$queryString = $this->_queryDelimiter . implode($this->_queryStringDelimiter, $escapedParams);
		}
		else
		{
			$queryString = '';
		}

		return 'http://' . $this->_host . ':' . $this->_port . $this->_path . $servlet . $queryString;
	}

	/**
	 * Construct the Full URLs for the three servlets we reference
	 */
	protected function _initUrls()
	{
		//Initialize our full servlet URLs now that we have server information
		$this->_updateUrl = $this->_constructUrl(self::UPDATE_SERVLET, array('wt' => self::SOLR_WRITER ));
		$this->_searchUrl = $this->_constructUrl(self::SEARCH_SERVLET);
		$this->_threadsUrl = $this->_constructUrl(self::THREADS_SERVLET, array('wt' => self::SOLR_WRITER ));

		$this->_urlsInited = true;
	}

	/**
	 * Central method for making a get operation against this Solr Server
	 *
	 * @param string $url
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If a non 200 response status is returned
	 */
	protected function _sendRawGet($url)
	{
		//$http_response_header is set by file_get_contents
		$response = new Apache_Solr_Response(@file_get_contents($url), $http_response_header);
		
		if ($response->getHttpStatus() != 200)
		{
			throw new Exception('"' . $response->getHttpStatus() . '" Status: ' . $response->getHttpStatusMessage());
		}

		return $response;
	}

	/**
	 * Central method for making a post operation against this Solr Server
	 *
	 * @param string $url
	 * @param string $rawPost
	 * @param string $contentType
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If a non 200 response status is returned
	 */
	protected function _sendRawPost($url, $rawPost, $contentType = 'text/xml; charset=UTF-8')
	{
		//ensure content type is correct
		stream_context_set_option($this->_postContext, 'http', 'header', 'Content-Type: ' . $contentType. "\r\n");

		//set the content
		stream_context_set_option($this->_postContext, 'http', 'content', $rawPost);

		//$http_response_header is set by file_get_contents
		
		for($i=0;$i<self::NUMBER_RESEND_REQUEST;$i++) {
			$response = new Apache_Solr_Response(@file_get_contents($url, false, $this->_postContext), $http_response_header);
			if ($response->getHttpStatus() == 200) {
				break;
			}
		}

		if ($response->getHttpStatus() != 200)
		{
			throw new Exception('"' . $response->getHttpStatus() . '" Status: ' . $response->getHttpStatusMessage());
		}

		return $response;
	}

	/**
	 * Returns the set host
	 *
	 * @return string
	 */
	public function getHost()
	{
		return $this->_host;
	}

	/**
	 * Set the host used. If empty will fallback to constants
	 *
	 * @param string $host
	 */
	public function setHost($host)
	{
		//Use the provided host or use the default
		if (empty($host))
		{
			throw new Exception('Host parameter is empty');
		}
		else
		{
			$this->_host = $host;
		}

		if ($this->_urlsInited)
		{
			$this->_initUrls();
		}
	}

	/**
	 * Get the set port
	 *
	 * @return integer
	 */
	public function getPort()
	{
		return $this->_port;
	}

	/**
	 * Set the port used. If empty will fallback to constants
	 *
	 * @param integer $port
	 */
	public function setPort($port)
	{
		//Use the provided port or use the default
		$port = (int) $port;

		if ($port <= 0)
		{
			throw new Exception('Port is not a valid port number'); 
		}
		else
		{
			$this->_port = $port;
		}

		if ($this->_urlsInited)
		{
			$this->_initUrls();
		}
	}

	/**
	 * Get the set path.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->_path;
	}

	/**
	 * Set the path used. If empty will fallback to constants
	 *
	 * @param string $path
	 */
	public function setPath($path)
	{
		$path = trim($path, '/');

		$this->_path = '/' . $path . '/';

		if ($this->_urlsInited)
		{
			$this->_initUrls();
		}
	}

	/**
	 * Set the string used to separate the path form the query string.
	 * Defaulted to '?'
	 *
	 * @param string $queryDelimiter
	 */
	public function setQueryDelimiter($queryDelimiter)
	{
		$this->_queryDelimiter = $queryDelimiter;
	}

	/**
	 * Set the string used to separate the parameters in thequery string
	 * Defaulted to '&'
	 *
	 * @param string $queryStringDelimiter
	 */
	public function setQueryStringDelimiter($queryStringDelimiter)
	{
		$this->_queryStringDelimiter = $queryStringDelimiter;
	}

	/**
	 * Call the /admin/ping servlet, can be used to quickly tell if a connection to the
	 * server is able to be made.
	 *
	 * @param float $timeout maximum time to wait for ping in seconds, -1 for unlimited (default is 5)
	 * @return float Actual time taken to ping the server, FALSE if timeout occurs
	 */
	public function ping($timeout = 5)
	{
		$timeout = (float) $timeout;

		if ($timeout <= 0)
		{
			$timeout = -1;
		}

		$start = microtime(true);

		//try to connect to the host with timeout
		$fp = fsockopen($this->_host, $this->_port, $errno, $errstr, $timeout);

		if ($fp)
		{
			//If we have a timeout set, then determine the amount of time we have left
			//in the request and set the stream timeout for the write operation
			if ($timeout > 0)
			{
				//do the calculation
				$writeTimeout = $timeout - (microtime(true) - $start);

				//check if we're out of time
				if ($writeTimeout <= 0)
				{
					return false;
				}

				//convert to microseconds and set the stream timeout
				$writeTimeoutInMicroseconds = (int) $writeTimeout * 1000000;
				stream_set_timeout($fp, 0, $writeTimeoutInMicroseconds);
			}

			$request = 	'HEAD ' . $this->_path . self::PING_SERVLET . ' HTTP/1.1' . "\r\n" .
						'host: ' . $this->_host . "\r\n" .
						'Connection: close' . "\r\n" .
						"\r\n";

			fwrite($fp, $request);

			//check the stream meta data to see if we timed out during the operation
			$metaData = stream_get_meta_data($fp);

			if (isset($metaData['timeout']))
			{
				fclose($fp);
				return false;
			}


			//if we have a timeout set and have made it this far, determine the amount of time
			//still remaining and set the timeout appropriately before the read operation
			if ($timeout > 0)
			{
				//do the calculation
				$readTimeout = $timeout - (microtime(true) - $start);

				//check if we've run out of time
				if ($readTimeout <= 0)
				{
					return false;
				}

				//convert to microseconds and set the stream timeout
				$readTimeoutInMicroseconds = $readTimeout * 1000000;
				stream_set_timeout($fp, 0, $readTimeoutInMicroseconds);
			}

			$response = fread($fp, 15);

			//check the stream meta data to see if we timed out during the operation
			$metaData = stream_get_meta_data($fp);

			if (isset($metaData['timeout']))
			{
				fclose($fp);
				return false;
			}

			//we made it, return the approximate ping time
			return microtime(true) - $start;
		}

		return false;
	}

	/**
	 * Call the /admin/threads servlet and retrieve information about all threads in the
	 * Solr servlet's thread group. Useful for diagnostics.
	 *
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function threads()
	{
		return $this->_sendRawGet($this->_threadsUrl);
	}

	/**
	 * Raw Add Method. Takes a raw post body and sends it to the update service.  Post body
	 * should be a complete and well formed "add" xml document.
	 *
	 * @param string $rawPost
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function add($rawPost)
	{
		return $this->_sendRawPost($this->_updateUrl, $rawPost);
	}

	/**
	 * Add a Solr Document to the index
	 *
	 * @param Apache_Solr_Document $document
	 * @param boolean $allowDups
	 * @param boolean $overwritePending
	 * @param boolean $overwriteCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function addDocument(DMC_Solr_Model_SolrServer_Document $document, $allowDups = false, $overwritePending = true, $overwriteCommitted = true)
	{
		$dupValue = $allowDups ? 'true' : 'false';
		$pendingValue = $overwritePending ? 'true' : 'false';
		$committedValue = $overwriteCommitted ? 'true' : 'false';

		$rawPost = '<add allowDups="' . $dupValue . '" overwritePending="' . $pendingValue . '" overwriteCommitted="' . $committedValue . '">';
		$rawPost .= $this->_documentToXmlFragment($document);
		$rawPost .= '</add>';
		return $this->add($rawPost);
	}

	/**
	 * Add an array of Solr Documents to the index all at once
	 *
	 * @param array $documents Should be an array of Apache_Solr_Document instances
	 * @param boolean $allowDups
	 * @param boolean $overwritePending
	 * @param boolean $overwriteCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function addDocuments($documents, $allowDups = false, $overwritePending = true, $overwriteCommitted = true)
	{
		$dupValue = $allowDups ? 'true' : 'false';
		$pendingValue = $overwritePending ? 'true' : 'false';
		$committedValue = $overwriteCommitted ? 'true' : 'false';
		$rawPost = '<add allowDups="' . $dupValue . '" overwritePending="' . $pendingValue . '" overwriteCommitted="' . $committedValue . '">';

		foreach ($documents as $document)
		{	
			if ($document instanceof DMC_Solr_Model_SolrServer_Document)
			{
				$rawPost .= $this->_documentToXmlFragment($document);
			}
		}
		$rawPost .= '</add>';
		
		return $this->add($rawPost);
	}

	/**
	 * Create an XML fragment appropriate for use inside a Solr add call
	 *
	 * @return string
	 */
	private function _documentToXmlFragment(DMC_Solr_Model_SolrServer_Document $document)
	{
		$xml = '<doc>';

		foreach ($document as $key => $value)
		{
			$key = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

			if (is_array($value))
			{
				foreach ($value as $multivalue)
				{
					$multivalue = htmlspecialchars($multivalue, ENT_NOQUOTES, 'UTF-8');

					$xml .= '<field name="' . $key . '">' . $multivalue . '</field>';
				}
			}
			else
			{
//				echo '<br/>'.$key.'  '.$value;
				$value = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');

				$xml .= '<field name="' . $key . '">' . $value . '</field>';
			}
		}

		$xml .= '</doc>';

		return $xml;
	}

	/**
	 * Send a commit command.  Will be synchronous unless both wait parameters are set
	 * to false.
	 *
	 * @param boolean $waitFlush
	 * @param boolean $waitSearcher
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function commit($waitFlush = true, $waitSearcher = true)
	{
		Mage::helper('solr/log')->addDebugMessage('Commit data');
		$flushValue = $waitFlush ? 'true' : 'false';
		$searcherValue = $waitSearcher ? 'true' : 'false';

		//$rawPost = '<commit waitFlush="' . $flushValue . '" waitSearcher="' . $searcherValue . '" />';
                $rawPost = '<commit />';

		return $this->_sendRawPost($this->_updateUrl, $rawPost);
	}

	/**
	 * Raw Delete Method. Takes a raw post body and sends it to the update service. Body should be
	 * a complete and well formed "delete" xml document
	 *
	 * @param string $rawPost
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function delete($rawPost)
	{
		return $this->_sendRawPost($this->_updateUrl, $rawPost);
	}

	/**
	 * Create a delete document based on document ID
	 *
	 * @param string $id
	 * @param boolean $fromPending
	 * @param boolean $fromCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function deleteById($id, $fromPending = true, $fromCommitted = true)
	{
		$pendingValue = $fromPending ? 'true' : 'false';
		$committedValue = $fromCommitted ? 'true' : 'false';

		//escape special xml characters
		$id = htmlspecialchars($id, ENT_NOQUOTES, 'UTF-8');

		$rawPost = '<delete fromPending="' . $pendingValue . '" fromCommitted="' . $committedValue . '"><id>' . $id . '</id></delete>';

		return $this->delete($rawPost);
	}

	/**
	 * Create a delete document based on a query and submit it
	 *
	 * @param string $rawQuery
	 * @param boolean $fromPending
	 * @param boolean $fromCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function deleteByQuery($rawQuery, $fromPending = true, $fromCommitted = true)
	{
		$pendingValue = $fromPending ? 'true' : 'false';
		$committedValue = $fromCommitted ? 'true' : 'false';

		//escape special xml characters
		$rawQuery = htmlspecialchars($rawQuery, ENT_NOQUOTES, 'UTF-8');

		$rawPost = '<delete fromPending="' . $pendingValue . '" fromCommitted="' . $committedValue . '"><query>' . $rawQuery . '</query></delete>';
		return $this->delete($rawPost);
	}

	/**
	 * Send an optimize command.  Will be synchronous unless both wait parameters are set
	 * to false.
	 *
	 * @param boolean $waitFlush
	 * @param boolean $waitSearcher
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function optimize($waitFlush = true, $waitSearcher = true)
	{
		Mage::helper('solr/log')->addDebugMessage('Optimize index');
		$flushValue = $waitFlush ? 'true' : 'false';
		$searcherValue = $waitSearcher ? 'true' : 'false';

		//$rawPost = '<optimize waitFlush="' . $flushValue . '" waitSearcher="' . $searcherValue . '" />';
                $rawPost = '<optimize />';

		return $this->_sendRawPost($this->_updateUrl, $rawPost);
	}

	/**
	 * Simple Search interface
	 *
	 * @param string $query The raw query string
	 * @param int $offset The starting offset for result documents
	 * @param int $limit The maximum number of result documents to return
	 * @param array $params key / value pairs for query parameters, use arrays for multivalued parameters
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function search($query, $offset = 0, $limit = 10, $params = array())
	{
		if (!is_array($params))
		{
			$params = array();
		}

		//construct our full parameters
		//sending the version is important in case the format changes
		$params['version'] = self::SOLR_VERSION;

		//common parameters in this interface
		$params['wt'] = self::SOLR_WRITER;
		$params['q'] = $query;
		$params['start'] = $offset;
		$params['rows'] = $limit;

		//escape all parameters appropriately for inclusion in the GET parameters
		$escapedParams = array();

		do
		{
			//because some parameters can be included multiple times, loop through all
			//params and include their value or their first array value. unset values as
			//they are fully added so that the params list can be iteratively added.
			//
			//NOTE: could be done all at once, but this way makes the query string more
			//readable at little performance cost
			foreach ($params as $key => &$value)
			{
				if (is_array($value))
				{
					//parameter has multiple values that need passed
					//array_shift pops off the first value in the array and also removes it
					$escapedParams[] = urlencode($key) . '=' . urlencode(array_shift($value));

					if (empty($value))
					{
						unset($params[$key]);
					}
				}
				else
				{
					//simple, single value case
					$escapedParams[] = urlencode($key) . '=' . urlencode($value);
					unset($params[$key]);
				}
			}
		} while (!empty($params));
		return $this->_sendRawGet($this->_searchUrl . $this->_queryDelimiter . implode($this->_queryStringDelimiter, $escapedParams));
	}
}