<?php
/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         3-7170 Ash Cres                                 */
/*  OF               Vancouver BC   V6P 3K7                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

//declare(strict_types=1);

namespace OneTeamSoftware\WooCommerce\Xml;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\SoapRequestBuilder')):

class SoapRequestBuilder extends XmlBuilder
{
	protected $namespace;

	public function __construct()
	{
		$this->namespace = array();
	}

	public function setNamespace($prefix, $uri)
	{
		$this->namespace['prefix'] = $prefix;
		$this->namespace['uri'] = $uri;
	}

	protected function createRootElement(\DomDocument &$document, array &$values)
	{
		$root = $document->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'SOAP-ENV:Envelope');

		if (!empty($this->namespace['uri'])) {
			$attributeName = 'xmlns';
			$attributeValue = $this->namespace['uri'];

			if (!empty($this->namespace['prefix'])) {
				$attributeName .= ':' . $this->namespace['prefix'];
			}

			$root->setAttribute($attributeName, $attributeValue);
		}

		$body = $document->createElement('SOAP-ENV:Body');
		$root->appendChild($body);
		$document->appendChild($root);

		return $body;
	}

	protected function createElement(\DomDocument &$document, \DomElement &$root, $nodeName)
	{
		if (!empty($this->namespace['prefix'])) {
			$nodeName = $this->namespace['prefix'] . ':' . $nodeName;
		}

		$node = $document->createElement($nodeName);
		$root->appendChild($node);

		return $node;
	}
}

endif;