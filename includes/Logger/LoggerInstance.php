<?php
/*********************************************************************/
/* PROGRAM    (C) 2021 FlexRC                                        */
/* PROPERTY   3-7170 Ash Cres                                        */
/* OF         Vancouver, BC V6P3K7                                   */
/*            CANADA                                                 */
/*            Voice (604) 800-7879                                   */
/*********************************************************************/

namespace OneTeamSoftware\WooCommerce\Logger;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\LoggerInstance')):

class LoggerInstance
{
	private static $instances = array();

	public static function &getInstance($id)
	{
		if (empty(self::$instances[$id])) {
			self::$instances[$id] = new Logger($id);
		}

		return self::$instances[$id];
	}
}

endif;