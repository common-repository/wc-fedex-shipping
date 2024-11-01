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

if (!class_exists(__NAMESPACE__ . '\\SoapResponseParser')):

class SoapResponseParser extends XmlParser
{
	protected function getNodeName($node)
	{
		$nodeName = parent::getNodeName($node);

		$nodeNameParts = explode(':', $nodeName, 2);
		if (count($nodeNameParts) == 2) {
			$nodeName = $nodeNameParts[1];
		}

		return $nodeName;
	}
}

endif;