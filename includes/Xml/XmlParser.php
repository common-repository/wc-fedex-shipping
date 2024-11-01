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

if (!class_exists(__NAMESPACE__ . '\\XmlParser')):

class XmlParser
{
	public function parse($source)
	{
		$document = new \DOMDocument();
		$document->loadXML($source);
		
		return $this->parseElement($document);
	}

	protected function parseElement($root)
	{
		if (!$root->hasChildNodes()) {
			return null;
		}

		if ($root->childNodes->length == 1) {
			$node = $root->childNodes->item(0);

			if ($node->nodeType == XML_TEXT_NODE) {
				return $node->nodeValue;
			}
		}

		$values = array();

		foreach ($root->childNodes as $node) {
			$nodeName = $this->getNodeName($node);
			if ($nodeName == '#text' || empty($nodeName)) {
				continue;
			}

			$value = $this->parseElement($node);

			if (isset($values[$nodeName])) {
				if (!is_array($values[$nodeName]) || !isset($values[$nodeName][0])) {
					$values[$nodeName] = array($values[$nodeName]);
				}

				$values[$nodeName][] = $value;
			} else {
				$values[$nodeName] = $value;
			}
		}

		return $values;
	}

	protected function getNodeName($node)
	{
		$nodeName = '';
		if (is_object($node)) {
			$nodeName = $node->nodeName;			
		}

		return $nodeName;
	}
}

endif;