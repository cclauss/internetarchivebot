<?php

/*
	Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive

	This file is part of IABot's Framework.

	IABot is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	InternetArchiveBot is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with InternetArchiveBot.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
*/

/**
 * @file
 * DB object
 * @author    Maximilian Doerr (Cyberpower678)
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
 */

/**
 * DB class
 * Manages all DB related parts of the bot
 * @author    Maximilian Doerr (Cyberpower678)
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
 */
class DB {

	/**
	 * Stores the mysqli db resource
	 *
	 * @var mysqli
	 * @access protected
	 */
	protected static $db;
	/**
	 * Stores the cached database for a fetched page
	 *
	 * @var array
	 * @access public
	 */
	public $dbValues = [];
	/**
	 * Stores the API object
	 *
	 * @var API
	 * @access public
	 */
	public $commObject;
	/**
	 * Duplicate of dbValues except it remains unchanged
	 *
	 * @var array
	 * @access protected
	 */
	protected $odbValues = [];

	/**
	 * Stores the cached DB values for a given page
	 *
	 * @var array
	 * @access protected
	 */
	protected $cachedPageResults = [];

	/**
	 * Stores checkpoint data for crash recovery
	 *
	 * @var array
	 * @static
	 * @access protected
	 */
	protected static $checkPoint = [];

	/**
	 * Constructor of the DB class
	 *
	 * @param API $commObject
	 *
	 * @access    public
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function __construct( API $commObject ) {
		$this->commObject = $commObject;
		//Load all URLs from the page
		$res = self::query( "SELECT externallinks_global.url_id, externallinks_global.paywall_id, url, archive_url, has_archive, live_state, unix_timestamp(last_deadCheck) AS last_deadCheck, archivable, archived, archive_failure, unix_timestamp(access_time) AS access_time, unix_timestamp(archive_time) AS archive_time, paywall_status, reviewed, notified
										 FROM externallinks_" . WIKIPEDIA . "
										 LEFT JOIN externallinks_global ON externallinks_global.url_id = externallinks_" .
		                    WIKIPEDIA . ".url_id
										 LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id = externallinks_paywall.paywall_id
										 WHERE `pageid` = '{$this->commObject->pageid}';"
		);
		if( $res !== false ) {
			//Store the results into the cache.
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( is_null( $result['url_id'] ) ) continue;
				$this->cachedPageResults[] = $result;
			}
			mysqli_free_result( $res );
		}
	}

	public static function getCheckpoint( $force = false ) {
		if( defined( 'NOCHECKPOINT' ) ) return [];

		if( empty( self::$checkPoint ) || $force ) {
			if( empty( UNIQUEID ) ) $query =
				"SELECT * FROM externallinks_checkpoints WHERE wiki = '" . WIKIPEDIA . "';";
			else $query = "SELECT * FROM externallinks_checkpoints WHERE wiki = '" . WIKIPEDIA . "' AND unique_id = '" .
			              UNIQUEID . "';";

			$res = self::query( $query );

			if( $res ) {
				while( $result = mysqli_fetch_assoc( $res ) ) {
					self::$checkPoint = $result;
					break;
				}
				mysqli_free_result( $res );

				if( empty( self::$checkPoint ) ) {
					# Look for legacy crash files and port them
					if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID ) ) $checkpoint =
						mysqli_escape_string( self::$db,
						                      file_get_contents( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID )
						);
					else $checkpoint = "";
					if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID . "c" ) ) $c =
						mysqli_escape_string( self::$db,
						                      file_get_contents( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID . "c" )
						);
					else $c = "";
					if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID . "stats" ) ) $stats =
						mysqli_escape_string( self::$db,
						                      file_get_contents( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID .
						                                         "stats"
						                      )
						);
					else $stats = "";
					if( empty( UNIQUEID ) ) $query =
						"INSERT INTO externallinks_checkpoints (`wiki`, `checkpoint`, `c`, `stats`) VALUES ( '" .
						WIKIPEDIA . "', '$checkpoint', '$c', '$stats' );";
					else $query =
						"INSERT INTO externallinks_checkpoints (`wiki`, `unique_id`, `checkpoint`, `c`, `stats`) VALUES ( '" .
						WIKIPEDIA . "', '" . UNIQUEID . "', '$checkpoint', '$c', '$stats' );";

					if( self::query( $query ) ) return self::getCheckpoint();
					else {
						echo "Failure to initialize checkpoint data.  Bot will exit.\n";
						exit( 1 );
					}
				}
			} else {
				echo "Failure to acquire checkpoint data.  Bot will exit.\n";
				exit( 1 );
			}

			if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID ) ) unlink( IAPROGRESS . "runfiles/" .
			                                                                             WIKIPEDIA . UNIQUEID
			);
			if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID . "c" ) ) unlink( IAPROGRESS .
			                                                                                   "runfiles/" . WIKIPEDIA .
			                                                                                   UNIQUEID . "c"
			);
			if( file_exists( IAPROGRESS . "runfiles/" . WIKIPEDIA . UNIQUEID . "stats" ) ) unlink( IAPROGRESS .
			                                                                                       "runfiles/" .
			                                                                                       WIKIPEDIA .
			                                                                                       UNIQUEID . "stats"
			);
		}

		return self::$checkPoint;
	}

	public static function checkpointCheckRun() {
		if( defined( 'NOCHECKPOINT' ) ) return true;

		$checkpoint = self::getCheckpoint();

		if( $checkpoint['run_state'] == 1 ) return true;
		else {
			if( time() >= strtotime( $checkpoint['next_run'] ) ) {
				$query =
					"UPDATE externallinks_checkpoints SET `run_state` = 1, `run_start` = CURRENT_TIMESTAMP, `next_run` = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 3 DAY) WHERE checkpoint_id = {$checkpoint['checkpoint_id']};";

				self::$checkPoint['run_state'] = 1;
				self::$checkPoint['run_start'] = date( 'Y-m-d H:i:s', time() );
				self::$checkPoint['run_start'] = date( 'Y-m-d H:i:s', strtotime( "+3 days" ) );

				return self::query( $query );
			} else {
				return strtotime( $checkpoint['next_run'] ) - time();
			}
		}
	}

	public static function checkpointEndRun() {
		if( defined( 'NOCHECKPOINT' ) ) return true;

		$checkpoint = self::getCheckpoint();

		$query =
			"UPDATE externallinks_checkpoints SET `run_state` = 0, `checkpoint` = '', `c` = '', `stats` = '' WHERE checkpoint_id = {$checkpoint['checkpoint_id']};";

		self::$checkPoint['run_state'] = 0;
		self::$checkPoint['checkpoint'] = '';
		self::$checkPoint['c'] = '';
		self::$checkPoint['stats'] = '';

		return self::query( $query );
	}

	public static function setCheckpoint( $data ) {
		if( defined( 'NOCHECKPOINT' ) ) return true;

		$checkpoint = self::getCheckpoint();

		$query = "UPDATE externallinks_checkpoints SET";
		foreach( $data as $key => $value ) {
			$query .= " `$key`='" . mysqli_escape_string( self::$db, $value ) . "'";
		}
		$query .= " WHERE checkpoint_id = {$checkpoint['checkpoint_id']};";

		if( self::query( $query ) ) {
			self::$checkPoint = array_replace( self::$checkPoint, $data );

			return true;
		} else return false;
	}

	/**
	 * Run the given SQL unless in test mode
	 *
	 * @access private
	 * @static
	 *
	 * @param string $query the query
	 * @param bool $multi
	 *
	 * @return mixed The result
	 */
	private static function query( $query, $multi = false, $dbNoSelect = false ) {
		if( !( self::$db instanceof mysqli ) ) self::connectDB( $dbNoSelect );
		if( TESTMODE ) {
			$executeQuery = !preg_match( '/(?:UPDATE|INSERT|REPLACE|DELETE)/i', $query );
		} else {
			$executeQuery = true;
		}
		if( !$executeQuery || IAVERBOSE ) echo "$query\n";
		if( $executeQuery ) {
			if( $multi ) {
				$response = mysqli_multi_query( self::$db, $query );
			} else {
				$response = mysqli_query( self::$db, $query );
			}

			if( $response === false ) {
				if( self::getError() == 2006 ) {
					self::reconnect();
					$response = self::query( $query, $multi );
				} else {
					echo "DB ERROR " . self::getError() . ": " . self::getError( true );
					echo "\nQUERY: $query\n";
				}
			}
		}

		if( $response ) {
			return $response;
		} elseif( !$executeQuery ) return true;
	}

	private static function connectDB( $noDBSelect = false ) {
		if( !( self::$db instanceof mysqli ) ) {
			self::$db = mysqli_init();
			mysqli_real_connect( self::$db, HOST, USER, PASS, '', PORT, '', ( IABOTDBSSL ?
				MYSQLI_CLIENT_SSL : 0 )
			);
			if( $noDBSelect === false ) mysqli_select_db( self::$db, DB );
		}
		if( !self::$db ) {
			throw new Exception( "Unable to connect to the database", 20000 );
		}
		mysqli_autocommit( self::$db, true );
		mysqli_set_charset( self::$db, "utf8" );
	}

	private static function getError( $text = false ) {
		if( $text === false ) {
			return mysqli_errno( self::$db );
		} else return mysqli_error( self::$db );
	}

	private static function reconnect() {
		if( self::$db instanceof mysqli ) mysqli_close( self::$db );
		self::$db = mysqli_init();
		mysqli_real_connect( self::$db, HOST, USER, PASS, DB, PORT, '', ( IABOTDBSSL ?
			MYSQLI_CLIENT_SSL : 0 )
		);
		if( !self::$db ) {
			throw new Exception( "Unable to connect to the database", 20000 );
		}
		mysqli_autocommit( self::$db, true );
	}

	/**
	 * Post details about a failed edit attempt to the log.
	 * Kills the program if database can't connect.
	 *
	 * @param string $wiki Wiki to fetch
	 * @param string $role Config group to fetch
	 * @param string $key Retrieve specific key
	 *
	 * @return bool True on success, false on failure
	 * @throws Exception
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public static function getConfiguration( $wiki, $role, $key = false ) {
		$returnArray = [];

		$query = "SELECT * FROM externallinks_configuration WHERE `config_wiki` = '" .
		         mysqli_escape_string( self::$db, $wiki ) . "' AND `config_type` = '" .
		         mysqli_escape_string( self::$db, $role ) . "'";
		if( $key !== false ) $query .= " AND `config_key` = '" . mysqli_escape_string( self::$db, $key ) . "'";
		$query .= " ORDER BY `config_id` ASC;";
		if( !( $res = self::query( $query ) ) ) {
			throw new Exception( "Unable to retrieve configuration", 40 );
		} else {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				if( $key !== false && $key == $result['config_key'] ) return unserialize( $result['config_data'] );
				$returnArray[$result['config_key']] = unserialize( $result['config_data'] );
			}
		}

		return $returnArray;
	}

	/**
	 * Post details about a failed edit attempt to the log.
	 * Kills the program if database can't connect.
	 *
	 * @param string $wiki Wiki to set
	 * @param string $role Config group to set
	 * @param string $key Set specific key
	 * @param string $data The value of the key to set.
	 * @param bool $onlyCreate Don't overwrite existing values.
	 *
	 * @return bool True on success, false on failure
	 * @throws Exception
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 */
	public static function setConfiguration( $wiki, $role, $key, $data, $onlyCreate = false ) {
		if( !is_null( $data ) ) {
			if( $onlyCreate ) {
				$query =
					"INSERT INTO externallinks_configuration ( `config_wiki`, `config_type`, `config_key`, `config_data` ) VALUES ('" .
					mysqli_escape_string( self::$db, $wiki ) . "', '" . mysqli_escape_string( self::$db, $role ) .
					"', '" .
					mysqli_escape_string( self::$db, $key ) . "', '" .
					mysqli_escape_string( self::$db, serialize( $data ) ) .
					"');";
			} else {
				$query =
					"REPLACE INTO externallinks_configuration ( `config_wiki`, `config_type`, `config_key`, `config_data` ) VALUES ('" .
					mysqli_escape_string( self::$db, $wiki ) . "', '" . mysqli_escape_string( self::$db, $role ) .
					"', '" .
					mysqli_escape_string( self::$db, $key ) . "', '" .
					mysqli_escape_string( self::$db, serialize( $data ) ) .
					"');";
			}
		} else {
			$query =
				"DELETE FROM externallinks_configuration WHERE `config_wiki` = '" .
				mysqli_escape_string( self::$db, $wiki ) .
				"' AND `config_type` = '" . mysqli_escape_string( self::$db, $role ) . "' AND `config_key` = '" .
				mysqli_escape_string( self::$db, $key ) . "';";
		}
		$res = ( self::query( $query ) || self::getError() === 1062 && $onlyCreate );

		return $res;
	}

	/**
	 * Post details about a failed edit attempt to the log.
	 * Kills the program if database can't connect.
	 *
	 * @param string $title Page title
	 * @param string $text Wikitext to be posted
	 * @param string $failReason Reason edit failed
	 *
	 * @return bool True on success, false on failure
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function logEditFailure( $title, $text, $failReason ) {
		$query =
			"INSERT INTO externallinks_editfaillog (`wiki`, `worker_id`, `page_title`, `attempted_text`, `failure_reason`) VALUES ('" .
			WIKIPEDIA . "', '" . UNIQUEID . "', '" . mysqli_escape_string( self::$db, $title ) . "', '" .
			mysqli_escape_string( self::$db, $text ) . "', '" . mysqli_escape_string( self::$db, $failReason ) . "');";
		if( !self::query( $query ) ) {
			echo "ERROR: Failed to post edit error to DB.\n";

			return false;
		} else return true;

	}

	/**
	 * Look for, or set, a cached version of an archive URL
	 *
	 * @param $url           string The short-form URL to lookup.
	 * @param $normalizedURL string The normalized URL to set for the short-form URL
	 *
	 * @access    public
	 * @static
	 * @return string|bool Returns the normalized URL, or false if it's not yet cached, or URL can't be set.
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function accessArchiveCache( $url, $normalizedURL = false ) {
		$return = false;
		if( $normalizedURL === false ) {
			$sql = "SELECT * FROM externallinks_archives WHERE `short_form_url` = '" .
			       mysqli_escape_string( self::$db, $url ) . "';";
			$res = self::query( $sql );
			if( $res ) {
				while( $result = mysqli_fetch_assoc( $res ) ) {
					$return = $result['normalized_url'];
				}
			}
			mysqli_free_result( $res );
		} else {
			if( empty( $normalizedURL ) ) return false;
			$sql = "INSERT INTO externallinks_archives (`short_form_url`, `normalized_url`) VALUES ('" .
			       mysqli_escape_string( self::$db, $url ) . "', '" .
			       mysqli_escape_string( self::$db, $normalizedURL ) . "')";
			$return = self::query( $sql );
		}

		return $return;
	}

	/**
	 * Checks for the existence of the needed tables
	 * and creates them if they don't exist.
	 * Program dies on failure.
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function checkDB( $mode = "no404" ) {

		if( $mode == "no404" ) {
			self::createPaywallTable();
			self::createGlobalELTable();
			self::createELTable();
			self::createArchiveFormCacheTable();
			self::createELScanLogTable();
			if( THROTTLECDXREQUESTS ) self::createAvailabilityRequestQueue();
		} elseif( $mode == "tarb" ) {
			self::createReadableTable();
			self::createGlobalBooksTable();
			self::createISBNBooksTable();
			self::createCollectionsBooksTable();
			self::createBooksRunsTable();
			self::createBooksWhitelistTable();
			self::createBooksRecommendationsTable();
		}
		self::createLogTable();
		self::createEditErrorLogTable();
		self::createWatchdogTable();
		self::createStatTable();
		self::createCheckpointsTable();
	}

	/**
	 * Create the paywall table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createPaywallTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_paywall` (
								  `paywall_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `domain` VARCHAR(255) NOT NULL,
								  `paywall_status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
								  PRIMARY KEY (`paywall_id` ASC),
								  UNIQUE INDEX `domain_UNIQUE` (`domain` ASC),
								  INDEX `PAYWALLSTATUS` (`paywall_status` ASC));
							  "
		) ) {
			echo "The paywall table exists\n\n";
		} else {
			echo "Failed to create a paywall table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the global externallinks table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createGlobalELTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_global` (
								  `url_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `paywall_id` INT UNSIGNED NOT NULL,
								  `url` VARCHAR(767) NOT NULL,
								  `archive_url` BLOB NULL,
								  `has_archive` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  `live_state` TINYINT UNSIGNED NOT NULL DEFAULT '4',
								  `last_deadCheck` TIMESTAMP NULL,
								  `archivable` TINYINT UNSIGNED NOT NULL DEFAULT '1',
								  `archived` TINYINT UNSIGNED NOT NULL DEFAULT '2',
								  `archive_failure` BLOB NULL DEFAULT NULL,
								  `access_time` TIMESTAMP NULL,
								  `archive_time` TIMESTAMP NULL DEFAULT NULL,
								  `reviewed` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  PRIMARY KEY (`url_id` ASC),
								  UNIQUE INDEX `url_UNIQUE` (`url` ASC),
								  INDEX `LIVE_STATE` (`live_state` ASC),
								  INDEX `LAST_DEADCHECK` (`last_deadCheck` ASC),
								  CONSTRAINT PAYWALLID FOREIGN KEY (paywall_id) REFERENCES externallinks_paywall (paywall_id) ON UPDATE cascade ON DELETE cascade,
								  INDEX `REVIEWED` (`reviewed` ASC),
								  INDEX `HASARCHIVE` (`has_archive` ASC),
								  INDEX `ISARCHIVED` (`archived` ASC),
								  INDEX `APIINDEX1` (`url_id` ASC, `live_state` ASC, `paywall_id` ASC),
								  INDEX `APIINDEX2` (`url_id` ASC, `live_state` ASC, `paywall_id` ASC, `archived` ASC),
								  INDEX `APIINDEX3` (`url_id` ASC, `live_state` ASC, `paywall_id` ASC, `reviewed` ASC),
								  INDEX `APIINDEX4` (`url_id` ASC, `live_state` ASC, `archived` ASC),
								  INDEX `APIINDEX5` (`url_id` ASC, `live_state` ASC, `reviewed` ASC),
								  INDEX `APIINDEX6` (`url_id` ASC, `archived` ASC, `reviewed` ASC),
								  INDEX `APIINDEX7` (`url_id` ASC, `has_archive` ASC, `paywall_id` ASC),
								  INDEX `APIINDEX8` (`url_id` ASC, `paywall_id` ASC, `archived` ASC),
								  INDEX `APIINDEX9` (`url_id` ASC, `paywall_id` ASC, `reviewed` ASC),
								  INDEX `APIINDEX10` (`url_id` ASC, `has_archive` ASC, `live_state` ASC, `paywall_id` ASC, `archived` ASC, `reviewed` ASC),
								  INDEX `APIINDEX11` (`url_id` ASC, `has_archive` ASC, `archived` ASC, `reviewed` ASC),
								  INDEX `APIINDEX12` (`url_id` ASC, `has_archive` ASC, `live_state` ASC, `paywall_id` ASC),
								  INDEX `APIINDEX13` (`url_id` ASC, `has_archive` ASC, `live_state` ASC),
								  INDEX `APIINDEX14` (`url_id` ASC, `has_archive` ASC, `live_state` ASC, `paywall_id` ASC, `reviewed` ASC),
								  INDEX `APIINDEX15` (`url_id` ASC, `has_archive` ASC, `live_state` ASC, `reviewed` ASC),
								  INDEX `APIINDEX16` (`url_id` ASC, `has_archive` ASC, `paywall_id` ASC, `reviewed` ASC));
							  "
		) ) {
			echo "The global external links table exists\n\n";
		} else {
			echo "Failed to create a global external links table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the wiki specific externallinks table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createELTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_" . WIKIPEDIA . "` (
								  `pageid` BIGINT UNSIGNED NOT NULL,
								  `url_id` BIGINT UNSIGNED NOT NULL,
								  `notified` TINYINT UNSIGNED NOT NULL DEFAULT '0',
								  PRIMARY KEY (`pageid` ASC, `url_id` ASC),
								  CONSTRAINT URLID_" . WIKIPEDIA . " FOREIGN KEY (url_id) REFERENCES externallinks_global (url_id) ON UPDATE CASCADE ON DELETE CASCADE);
							  "
		) ) {
			echo "The " . WIKIPEDIA . " external links table exists\n\n";
		} else {
			echo "Failed to create an external links table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the archive short form cache table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createArchiveFormCacheTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_archives` (
								  `form_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `short_form_url` VARCHAR(767) NOT NULL,
								  `normalized_url` BLOB NOT NULL,
								  PRIMARY KEY (`form_id` ASC),
								  UNIQUE INDEX `short_url_UNIQUE` (`short_form_url` ASC));
							  "
		) ) {
			echo "The archive cache table exists\n\n";
		} else {
			echo "Failed to create an archive cache table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the scan log table for links
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createELScanLogTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_scan_log` (
								  `scan_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `url_id` BIGINT UNSIGNED NOT NULL,
								  `scan_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `scanned_dead` TINYINT(1) NOT NULL,
								  `host_machine` VARCHAR(100) NOT NULL,
								  `external_ip` VARCHAR(39) NOT NULL,
								  `reported_code` INT(4) NOT NULL,
								  `reported_error` VARCHAR(255) NULL,
								  `request_data` BLOB NOT NULL,
								  PRIMARY KEY (`scan_id` ASC),
								  CONSTRAINT URLID_scan_log FOREIGN KEY (url_id) REFERENCES externallinks_global (url_id) ON UPDATE CASCADE ON DELETE CASCADE,
								  INDEX `RESULT` (`scanned_dead` ASC ),
								  INDEX `HOST` (`host_machine` ASC ),
								  INDEX `IP` (`external_ip` ASC ),
								  INDEX `TIMESTAMP` (`scan_time` ASC),
								  INDEX `STATUSCODE` (`reported_code` ASC),
								  INDEX `ERROR` (`reported_error` ASC));
							  "
		) ) {
			echo "The external links scan log exists\n\n";
		} else {
			echo "Failed to create a external links scan log to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the checkpoints table for crash recovery
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createCheckpointsTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_checkpoints` (
						    `checkpoint_id` INT(6) NOT NULL AUTO_INCREMENT,
						    `unique_id` VARCHAR(15),
						    `wiki` VARCHAR(45) NOT NULL,
						    `checkpoint` BLOB NOT NULL,
						    `c` BLOB NOT NULL,
						    `stats` BLOB NOT NULL,
						    `run_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
						    `next_run` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL FLOOR(RAND() * 72) HOUR),
						    `run_state` INT(1) NOT NULL DEFAULT 0,
						    PRIMARY KEY (`checkpoint_id` ASC),
						    UNIQUE INDEX `WIKIWORKER` (`wiki` ASC, `unique_id` ASC)
						);"
		) ) {
			echo "The external links checkpoints table exists\n\n";
		} else {
			echo "Failed to create a external links checkpoints table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the table that queues requests to be made to the availability API
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createAvailabilityRequestQueue() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_availability_requests` (
								  `request_id` BIGINT NOT NULL AUTO_INCREMENT,
								  `payload` BLOB NOT NULL,
								  `request_status` TINYINT(1) NOT NULL DEFAULT 0,
								  `request_timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `request_update` TIMESTAMP NULL,
								  `response_data` BLOB NULL,
								  PRIMARY KEY (`request_id` ASC ),
								  INDEX `STATUS` (`request_status` ASC),
								  INDEX `REQUESTTIME` (`request_timestamp` ASC),
								  INDEX `REQUESTUPDATE` (`request_update` ASC));
							  "
		) ) {
			echo "The availability table exists\n\n";
		} else {
			echo "Failed to create an availability table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the wiki readable table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createReadableTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `readable_" . WIKIPEDIA . "` (
								  `entry_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `pageid` BIGINT NOT NULL,
								  `type` ENUM ('isbn', 'arxiv', 'doi', 'pmid', 'pmc') NOT NULL,
								  `identifier` VARCHAR(128) NOT NULL,
								  `reference_type` ENUM ('magic', 'template', 'cite') NOT NULL,
								  `instances_found` INT NOT NULL,
								  `instances_linked` INT DEFAULT 0 NOT NULL,
								  `instances_not_linked` INT DEFAULT 0 NOT NULL,
								  `instances_google` INT DEFAULT 0 NOT NULL,
								  `instances_whitelist` INT DEFAULT 0 NOT NULL,
								  `instances_not_linked_with_pages` INT DEFAULT 0 NOT NULL,
								  `instances_with_pages` INT DEFAULT 0 NOT NULL,
								  PRIMARY KEY (`entry_id` ASC),
								  UNIQUE INDEX `UNIQUE` (pageid, type, identifier, reference_type),
								  INDEX `Identifier` (`identifier` ASC),
								  INDEX `InstanceCount` (`instances_found` ASC),
								  INDEX `PageID` (`pageid` ASC),
								  INDEX `ReferenceType` (`reference_type` ASC),
								  INDEX `Type` (`type` ASC),
								  INDEX `instances_linked_index` (`instances_linked` ASC),
								  INDEX `instances_not_linked_index` (`instances_not_linked` ASC),
								  INDEX `instances_google_index` (`instances_google` ASC),
								  INDEX `instances_whitelist_index` (`instances_whitelist` ASC));
							  "
		) ) {
			echo "The readable table exists\n\n";
		} else {
			echo "Failed to create a readable table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the global books table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createGlobalBooksTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_global` (
								  `book_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `license` BLOB NOT NULL,
								  `identifier` VARCHAR(255) NOT NULL,
								  `title` VARBINARY(700) NOT NULL,
								  `page_count` INT NOT NULL,
								  `creator` VARBINARY(400) NULL,
								  `publisher` VARBINARY(400) NULL,
								  `language` VARCHAR(60) NULL,
								  `volume` VARCHAR(10) NULL,
								  `conflated` TINYINT(1) DEFAULT 0 NOT NULL,
								  PRIMARY KEY (`book_id` ASC),
								  UNIQUE INDEX `IDENT` (identifier),
								  INDEX `CONFLATED` (`conflated` ASC),
								  INDEX `CREATOR` (`creator` ASC),
								  INDEX `LANG` (`language` ASC),
								  INDEX `PAGES` (`page_count` ASC),
								  INDEX `PUBLISHER` (`publisher` ASC),
								  INDEX `TITLE` (`title` ASC));
							  "
		) ) {
			echo "The global books table exists\n\n";
		} else {
			echo "Failed to create a global books table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the ISBN books table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createISBNBooksTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_isbn` (
								  `book_id` BIGINT UNSIGNED NOT NULL,
								  `isbn` VARCHAR(13) NOT NULL,
								  `duped` TINYINT(1) DEFAULT 0 NOT NULL,
								  PRIMARY KEY (book_id, isbn),
								  INDEX `DUP` (`duped` ASC),
								  CONSTRAINT ID_isbn FOREIGN KEY (book_id) REFERENCES books_global (book_id) ON UPDATE CASCADE ON DELETE CASCADE,
								  INDEX `ISBN` (`isbn` ASC));
							  "
		) ) {
			echo "The ISBN books table exists\n\n";
		} else {
			echo "Failed to create a ISBN books table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the global books table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createCollectionsBooksTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_collection_members` (
								  `book_id` BIGINT UNSIGNED NOT NULL,
								  `collection` VARBINARY(700) NOT NULL,
								  PRIMARY KEY (book_id, collection),
								  INDEX `COLLECTION` (`collection` ASC),
								  CONSTRAINT ID_collection_members FOREIGN KEY (book_id) REFERENCES books_global (book_id) ON UPDATE CASCADE ON DELETE CASCADE);
							  "
		) ) {
			echo "The collections books table exists\n\n";
		} else {
			echo "Failed to create a collections books table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the global runs table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createBooksRunsTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_runs` (
								  `group` VARCHAR(4) NOT NULL,
								  `object` VARBINARY(700) NOT NULL,
								  `last_run` TIMESTAMP NULL,
								  `last_indexing` TIMESTAMP NULL,
								  PRIMARY KEY (`group`, `object`));
							  "
		) ) {
			echo "The book runs table exists\n\n";
		} else {
			echo "Failed to create a book runs table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the global runs table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createBooksWhitelistTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_whitelist` (
								  `whitelist_id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
								  `url_fragment` varchar(255) NOT NULL,
								  `description` varbinary(255) NOT NULL,
								  UNIQUE INDEX `fragment` (`url_fragment` ASC));
							  "
		) ) {
			echo "The book whitelist table exists\n\n";
		} else {
			echo "Failed to create a book whitelist table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the table that recommends to users what pages to edit with TARB
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createBooksRecommendationsTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `books_recommended_articles` (
								  `wiki` VARCHAR(45) NOT NULL,
								  `pageid` BIGINT NOT NULL,
								  `potential_links` INT UNSIGNED NOT NULL DEFAULT 0,
								  PRIMARY KEY (`wiki` ASC, `pageid` ASC ),
								  INDEX `COUNT` (`potential_links` ASC));
							  "
		) ) {
			echo "The TARB recommendations table exists\n\n";
		} else {
			echo "Failed to create a TARB recommendations table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the logging table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createLogTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_log` (
								  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(45) NOT NULL,
								  `worker_id` VARCHAR(255) NULL DEFAULT NULL,
								  `run_start` TIMESTAMP NOT NULL,
								  `run_end` TIMESTAMP NOT NULL,
								  `pages_analyzed` INT UNSIGNED NOT NULL,
								  `pages_modified` INT UNSIGNED NOT NULL,
								  `sources_analyzed` BIGINT(12) UNSIGNED NOT NULL,
								  `sources_rescued` INT UNSIGNED NOT NULL,
								  `sources_tagged` INT UNSIGNED NOT NULL,
								  `sources_archived` BIGINT(12) UNSIGNED NOT NULL,
								  `sources_wayback` INT UNSIGNED NOT NULL DEFAULT 0,
								  `sources_other` INT UNSIGNED NOT NULL DEFAULT 0,
								  PRIMARY KEY (`log_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `RUNLENGTH` (`run_end` ASC, `run_start` ASC),
								  INDEX `PANALYZED` (`pages_analyzed` ASC),
								  INDEX `PMODIFIED` (`pages_modified` ASC),
								  INDEX `SANALYZED` (`sources_analyzed` ASC),
								  INDEX `SRESCUED` (`sources_rescued` ASC),
								  INDEX `STAGGED` (`sources_tagged` ASC),
								  INDEX `SARCHIVED` (`sources_archived` ASC),
								  INDEX `SWAYBACK` (`sources_wayback` ASC),
								  INDEX `SOTHER` (`sources_other` ASC))
								AUTO_INCREMENT = 0;
							  "
		) ) {
			echo "A log table exists\n\n";
		} else {
			echo "Failed to create a log table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the edit error log table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createEditErrorLogTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_editfaillog` (
								  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `wiki` VARCHAR(255) NOT NULL,
								  `worker_id` VARCHAR(255) NULL,
								  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `page_title` VARCHAR(255) NOT NULL,
								  `attempted_text` BLOB NOT NULL,
								  `failure_reason` VARCHAR(1000) NOT NULL,
								  PRIMARY KEY (`log_id`),
								  INDEX `WIKI` (`wiki` ASC),
								  INDEX `WORKERID` (`worker_id` ASC),
								  INDEX `TIMESTAMP` (`timestamp` ASC),
								  INDEX `REASON` (`failure_reason` ASC),
								  INDEX `PAGETITLE` (`page_title` ASC));
						       "
		) ) {
			echo "The edit error log table exists\n\n";
		} else {
			echo "Failed to create an edit error log table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the table that watches all active processes on all hosts
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createWatchdogTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_watchdog` (
								  `host` VARCHAR(100) NOT NULL,
								  `pid` INT NOT NULL,
								  `last_heartbeat` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
								  `wiki` VARCHAR(255) DEFAULT NULL,			  
								  `job` VARCHAR(255) DEFAULT NULL,
								  `data` BLOB DEFAULT NULL,
								  PRIMARY KEY ( `host` ASC, `pid` ASC ),
								  INDEX `PING` ( `last_heartbeat` ASC ),
								  INDEX `WIKI` ( `wiki` ASC ),
								  INDEX `JOB` ( `job` ASC ),
    							  UNIQUE INDEX `TIDY` ( `host` ASC, `wiki` ASC, `job` ASC ));
							  "
		) ) {
			echo "The watchdog table exists\n\n";
		} else {
			echo "Failed to create a watchdog table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	/**
	 * Create the statistics table
	 * Kills the program on failure
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createStatTable() {
		if( self::query( "CREATE TABLE IF NOT EXISTS `externallinks_statistics` (
									`stat_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
									`stat_wiki` VARCHAR(45) NOT NULL,
									`stat_timestamp` TIMESTAMP DEFAULT CURRENT_DATE NOT NULL,
									`stat_year` INT(4) DEFAULT YEAR(CURRENT_DATE) NOT NULL,
									`stat_month` INT(2) DEFAULT MONTH(CURRENT_DATE) NOT NULL,
									`stat_day` INT(2) DEFAULT DAY(CURRENT_DATE ) NOT NULL,
									`stat_key` VARCHAR(45) NOT NULL,
									`stat_value` BIGINT DEFAULT 0 NOT NULL,
									PRIMARY KEY (`stat_id`),
									UNIQUE INDEX `ENTRIES` (`stat_wiki`, `stat_timestamp`, `stat_key`),
									INDEX `STATDAY` (`stat_day` ASC),
									INDEX `STATKEYS` (`stat_key`),
									INDEX `STATMONTH` (`stat_month` ASC),
									INDEX `STATS` (`stat_value` ASC),
									INDEX `STATTIMES` (`stat_timestamp` ASC),
									INDEX `STATYEAR` (`stat_year` ASC))
								AUTO_INCREMENT = 0;"
		) ) {
			echo "A stat table exists\n\n";
		} else {
			echo "Failed to create a stat table to use.\nThis table is vital for the operation of this bot. Exiting...";
			exit( 10000 );
		}
	}

	public static function seekWatchDog( $wiki, $job, $timeout = '-5 minutes' ) {
		$timeout = date( 'Y-m-d H:i:s', $timeout );

		$sql = "SELECT * FROM externallinks_watchdog WHERE `wiki` = '" . mysqli_escape_string( self::$db, $wiki ) .
		       "' AND `job` = '" . mysqli_escape_string( self::$db, $job ) . "' AND `last_heartbeat` >= '$timeout';";

		$res = self::query( $sql );

		$returnArray = [];

		while( $result = mysqli_fetch_assoc( $res ) ) {
			$returnArray[] = $result;
		}

		return $returnArray;
	}

	/**
	 * Create the watchdog entry for this process
	 *
	 * @access    public
	 * @static
	 * @return bool
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function setWatchDog( $job, $data = null ) {
		$host = gethostname();
		$pid = getmypid();

		$sql = "REPLACE INTO externallinks_watchdog (`host`,`pid`,`wiki`,`job`,`data`) VALUES ('" .
		       mysqli_escape_string( self::$db, $host ) . "', $pid, '" . mysqli_escape_string( self::$db, WIKIPEDIA )
		       . "', '" . mysqli_escape_string( self::$db, $job ) . "', ";
		if( $data === false || is_null( $data ) ) {
			$sql .= "NULL";
		} else $sql .= "'" . mysqli_escape_string( self::$db, serialize( $data ) ) . "'";

		$sql .= ");";

		return self::query( $sql );
	}

	/**
	 * Update the watchdog entry for this process
	 *
	 * @access    public
	 * @static
	 * @return bool
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function pingWatchDog( $data = null ) {
		$host = gethostname();
		$pid = getmypid();

		$sql = "UPDATE externallinks_watchdog SET last_heartbeat = CURRENT_TIMESTAMP";
		if( $data === false ) {
			$sql .= ", data = NULL";
		} elseif( !is_null( $data ) ) {
			$sql .= ", data = '" . mysqli_escape_string( self::$db, serialize( $data ) ) .
			        "'";
		}

		$sql .= " WHERE host = '" . mysqli_escape_string( self::$db, $host ) . "' AND pid = $pid;";

		return self::query( $sql );
	}

	/**
	 * Delete the watchdog entry for this process
	 *
	 * @access    public
	 * @static
	 * @return bool
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function unsetWatchDog() {
		$host = gethostname();
		$pid = getmypid();

		$sql = "DELETE FROM externallinks_watchdog WHERE host = '" . mysqli_escape_string( self::$db, $host ) .
		       "' AND pid = $pid;";

		return self::query( $sql );
	}

	public static function updateAvailabilityRequest( $requestID, $status = null, $data = null ) {
		if( is_null( $status ) && is_null( $data ) ) return false;

		$sql = "UPDATE externallinks_availability_requests SET request_update = CURRENT_TIMESTAMP";

		if( $status === true ) {
			$sql .= ", request_status = 1";
		} elseif( $status === false ) $sql .= ", request_status = 2";

		if( !is_null( $data ) ) {
			$sql .= ", response_data = '" . mysqli_escape_string( self::$db, serialize( $data ) ) .
			        "'";
		}

		$sql .= " WHERE request_id = $requestID;";

		return self::query( $sql );
	}

	/**
	 * Queues up requests to be made to the availability API
	 *
	 * @param $post Payload to be passed to the API
	 *
	 * @access    public
	 * @static
	 * @return bool|int|string
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function addAvailabilityRequest( $post ) {
		if( empty( $post ) ) return false;

		$sql = "INSERT INTO externallinks_availability_requests (`payload`) VALUES ('" . mysqli_escape_string(
				self::$db,
				$post
			) . "');";

		if( self::query( $sql ) ) {
			return mysqli_insert_id( self::$db );
		} else return false;
	}

	/**
	 * Retrieve all pending requests
	 *
	 * @access    public
	 * @static
	 * @return array
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function getPendingAvailabilityRequests() {
		$sql = "SELECT * FROM externallinks_availability_requests WHERE request_status = 0;";

		$returnArray = [];

		if( $res = self::query( $sql ) ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$returnArray[$result['request_id']] = $result;
			}
		}

		return $returnArray;
	}

	/**
	 * Retrieve all requested IDs
	 *
	 * @access    public
	 * @static
	 * @return array|bool
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function getAvailabilityRequestIDs( $ids, $failIfPending = false, $clearOnSuccess = false ) {
		$sql = "SELECT * FROM externallinks_availability_requests WHERE";

		if( $failIfPending ) $sql .= " request_status > 0 AND";

		$idSnippet = " request_id IN ('" . implode( '\', \'', $ids ) . "');";

		$sql .= $idSnippet;

		$returnArray = [];
		$requestsPending = false;

		if( $res = self::query( $sql ) ) {
			while( $result = mysqli_fetch_assoc( $res ) ) {
				$returnArray[$result['request_id']] = $result;
				if( $result['request_status'] == 0 ) $requestsPending = true;
				while( ( $tid = array_search( $result['request_id'], $ids ) ) !== false ) unset( $ids[$tid] );
			}
		}

		if( $failIfPending && !empty( $ids ) ) return false;

		if( $clearOnSuccess && !$requestsPending ) {
			$sql = "DELETE FROM externallinks_availability_requests WHERE$idSnippet";
			self::query( $sql );
		}

		return $returnArray;
	}

	/**
	 * Creates a table to store configuration values
	 *
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 *
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public static function createConfigurationTable() {
		$sql = "CREATE DATABASE IF NOT EXISTS " . DB . ";";
		if( !self::query( $sql, false, true ) ) {
			echo "ERROR - " . mysqli_errno( self::$db ) . ": " . mysqli_error( self::$db ) . "\n";
			echo "Error encountered while creating the database.  Exiting...\n";
			exit( 1 );
		}

		if( !mysqli_select_db( self::$db, DB ) ) {
			echo "ERROR - " . mysqli_errno( self::$db ) . ": " . mysqli_error( self::$db ) . "\n";
			echo "Error encountered while switching to the database.  Exiting...\n";
			exit( 1 );
		}

		if( !self::query( "CREATE TABLE IF NOT EXISTS `externallinks_configuration` (
								  `config_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
								  `config_type` VARCHAR(45) NOT NULL,
								  `config_key` VARBINARY(255) NOT NULL,
								  `config_wiki` VARCHAR(45) NOT NULL,
								  `config_data` BLOB NOT NULL,
								  PRIMARY KEY (`config_id` ASC),
								  UNIQUE INDEX `unique_CONFIG` (`config_wiki`, `config_type`, `config_key` ASC),
								  INDEX `TYPE` (`config_type` ASC),
								  INDEX `WIKI` (`config_wiki` ASC),
								  INDEX `KEY` (`config_key` ASC));"
		) ) {
			echo "Unable to create a global configuration table.  Table is required to setup the bot.  Exiting...\n";
			exit( 10000 );
		}
	}

	/**
	 * Generates a log entry and posts it to the bot log on the DB
	 * @access    public
	 * @static
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 * @global $linksAnalyzed , $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $pagesAnalyzed,
	 *                        $pagesModified
	 */
	public static function generateLogReport() {
		global $linksAnalyzed, $linksArchived, $linksFixed, $linksTagged, $runstart, $runend, $pagesAnalyzed, $pagesModified, $waybackadded, $otheradded;
		$query =
			"INSERT INTO externallinks_log ( `wiki`, `worker_id`, `run_start`, `run_end`, `pages_analyzed`, `pages_modified`, `sources_analyzed`, `sources_rescued`, `sources_tagged`, `sources_archived`, `sources_wayback`, `sources_other` )\n";
		$query .= "VALUES ('" . WIKIPEDIA . "', '" . UNIQUEID . "', '" . date( 'Y-m-d H:i:s', $runstart ) . "', '" .
		          date( 'Y-m-d H:i:s', $runend ) .
		          "', '$pagesAnalyzed', '$pagesModified', '$linksAnalyzed', '$linksFixed', '$linksTagged', '$linksArchived', '$waybackadded', '$otheradded');";
		self::query( $query );
	}

	public function logScanResults( $urlID, $isDead, $ip, $hostname, $httpCode, $curlInfo, $error = '' ) {
		$sql =
			"INSERT INTO externallinks_scan_log (`url_id`,`scanned_dead`,`host_machine`,`external_ip`,`reported_code`,`reported_error`,`request_data`) VALUES ( $urlID," .
			( is_null( $isDead ) ? 2 : (int) (bool) $isDead ) . ", '$hostname', '$ip', $httpCode, " . ( empty( $error
			) ? "NULL" : "'$error'" ) .
			", '" .
			mysqli_escape_string( self::$db, serialize( $curlInfo ) ) . "' );";

		return self::query( $sql, false );
	}

	/**
	 * Insert contents of self::dbValues back into the DB
	 * and delete the unused cached values
	 *
	 * @access    public
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function updateDBValues() {
		$this->checkForUpdatedValues();

		$query = "";
		$updateQueryPaywall = "";
		$updateQueryGlobal = "";
		$updateQueryLocal = "";
		$deleteQuery = "";
		$insertQueryPaywall = "";
		$insertQueryGlobal = "";
		$insertQueryLocal = "";
		if( !empty( $this->dbValues ) ) {
			foreach( $this->dbValues as $id => $values ) {
				$url = mysqli_escape_string( self::$db, $values['url'] );
				$domain = mysqli_escape_string( self::$db, parse_url( $values['url'], PHP_URL_HOST ) );
				$values = $this->sanitizeValues( $values );
				//Aggregate all the entries of page that do not yet exist on the local table.
				if( isset( $values['createlocal'] ) ) {
					unset( $values['createlocal'] );
					//Aggregate all the URLs that do not exist on the global table.
					if( isset( $values['createglobal'] ) ) {
						unset( $values['createglobal'] );
						//Aggregate all the paywall domains that do not exist on the paywall table.
						if( isset( $values['createpaywall'] ) ) {
							unset( $values['createpaywall'] );
							if( empty( $insertQueryPaywall ) ) {
								$insertQueryPaywall =
									"INSERT IGNORE INTO `externallinks_paywall`\n\t(`domain`, `paywall_status`)\nVALUES\n";
							}
							// Aggregate unique domain names to insert into externallinks_paywall
							if( !isset( $tipAssigned ) || !in_array( $domain, $tipAssigned ) ) {
								$tipValues[] = [
									'domain' => $domain, 'paywall_status' => ( isset( $values['paywall_status'] ) ?
										$values['paywall_status'] : null )
								];
								$tipAssigned[] = $domain;   //Makes sure to not create duplicate key errors.
							}
						}
						$tigFields = [
							'reviewed', 'url', 'archive_url', 'has_archive', 'live_state', 'last_deadCheck',
							'archivable', 'archived', 'archive_failure', 'access_time', 'archive_time', 'paywall_id'
						];
						$insertQueryGlobal =
							"INSERT IGNORE INTO `externallinks_global`\n\t(`" . implode( "`, `", $tigFields ) . "`)\nVALUES\n";
						if( !isset( $tigAssigned ) || !in_array( $values['url'], $tigAssigned ) ) {
							$temp = [];
							foreach( $tigFields as $field ) {
								if( $field == "paywall_id" ) continue;
								if( isset( $values[$field] ) ) $temp[$field] = $values[$field];
							}
							$temp['domain'] = $domain;
							$tigValues[] = $temp;
							$tigAssigned[] = $values['url']; //Makes sure to not create duplicate key errors.
						}
					}
					$tilFields = [ 'notified', 'pageid', 'url_id' ];
					$insertQueryLocal =
						"INSERT IGNORE INTO `externallinks_" . WIKIPEDIA . "`\n\t(`" . implode( "`, `", $tilFields ) .
						"`)\nVALUES\n";
					if( !isset( $tilAssigned ) || !in_array( $values['url'], $tilAssigned ) ) {
						$temp = [];
						foreach( $tilFields as $field ) {
							if( $field == "url_id" ) continue;
							if( isset( $values[$field] ) ) $temp[$field] = $values[$field];
						}
						$temp['url'] = $values['url'];
						$tilValues[] = $temp;
						$tilAssigned[] = $values['url'];    //Makes sure to not create duplicate key errors.
					}
				}
				//Aggregate all entries needing updating on the paywall table
				if( isset( $values['updatepaywall'] ) ) {
					unset( $values['updatepaywall'] );
					if( empty( $updateQueryPaywall ) ) {
						$tupfields = [ 'paywall_status' ];
						$updateQueryPaywall = "UPDATE `externallinks_paywall`\n";
					}
					$tupValues[] = $values;
				}
				//Aggregate all entries needing updating on the global table
				if( isset( $values['updateglobal'] ) ) {
					unset( $values['updateglobal'] );
					if( empty( $updateQueryGlobal ) ) {
						$tugfields = [
							'archive_url', 'has_archive', 'live_state', 'last_deadCheck', 'archivable', 'archived',
							'archive_failure', 'access_time', 'archive_time', 'reviewed'
						];
						$updateQueryGlobal = "UPDATE `externallinks_global`\n";
					}
					$tugValues[] = $values;
				}
				//Aggregate all entries needing updating on the local table
				if( isset( $values['updatelocal'] ) ) {
					unset( $values['updatelocal'] );
					if( empty( $updateQueryLocal ) ) {
						$tulfields = [ 'notified' ];
						$updateQueryLocal = "UPDATE `externallinks_" . WIKIPEDIA . "`\n";
					}
					$tulValues[] = $values;
				}
			}
			//Create an INSERT statement for the paywall table if needed.
			if( !empty( $insertQueryPaywall ) ) {
				$comma = false;
				foreach( $tipValues as $value ) {
					if( $comma === true ) $insertQueryPaywall .= "),\n";
					$insertQueryPaywall .= "\t(";
					$insertQueryPaywall .= "'{$value['domain']}', ";
					if( is_null( $value['paywall_status'] ) ) {
						$insertQueryPaywall .= "DEFAULT";
					} else $insertQueryPaywall .= "'{$value['paywall_status']}'";
					$comma = true;
				}
				$insertQueryPaywall .= ");\n";
				$query .= $insertQueryPaywall;
			}
			//Create and INSERT statement for the global table if needed.
			if( !empty( $insertQueryGlobal ) ) {
				$comma = false;
				foreach( $tigValues as $value ) {
					if( $comma === true ) $insertQueryGlobal .= "),\n";
					$insertQueryGlobal .= "\t(";
					foreach( $tigFields as $field ) {
						if( $field == "paywall_id" ) continue;
						if( isset( $value[$field] ) ) {
							$insertQueryGlobal .= "'{$value[$field]}', ";
						} else $insertQueryGlobal .= "DEFAULT, ";
					}
					$insertQueryGlobal .= "(SELECT paywall_id FROM externallinks_paywall WHERE `domain` = '{$value['domain']}')";
					$comma = true;
				}
				$insertQueryGlobal .= ");\n";
				$query .= $insertQueryGlobal;
			}
			//Create and INSERT statement for the local table if needed.
			if( !empty( $insertQueryLocal ) ) {
				$comma = false;
				foreach( $tilValues as $value ) {
					if( $comma === true ) $insertQueryLocal .= "),\n";
					$insertQueryLocal .= "\t(";
					foreach( $tilFields as $field ) {
						if( $field == "pageid" ) continue;
						if( $field == "url_id" ) continue;
						if( isset( $value[$field] ) ) {
							$insertQueryLocal .= "'{$value[$field]}', ";
						} else $insertQueryLocal .= "DEFAULT, ";
					}
					$insertQueryLocal .= "'{$this->commObject->pageid}', (SELECT url_id FROM externallinks_global WHERE `url` = '{$value['url']}')";
					$comma = true;
				}
				$insertQueryLocal .= ");\n";
				$query .= $insertQueryLocal;
			}
			//Create an UPDATE statement for the paywall table if needed.
			if( !empty( $updateQueryPaywall ) ) {
				$updateQueryPaywall .= "\tSET ";
				$IDs = [];
				$updateQueryPaywall .= "`paywall_status` = CASE `paywall_id`\n";
				foreach( $tupValues as $value ) {
					if( isset( $value['paywall_status'] ) ) {
						$updateQueryPaywall .= "\t\tWHEN '{$value['paywall_id']}' THEN '{$value['paywall_status']}'\n";
					} else $updateQueryPaywall .= "\t\tWHEN '{$value['paywall_id']}' THEN DEFAULT\n";
					$IDs[] = $value['paywall_id'];
				}
				$updateQueryPaywall .= "\tEND\n";
				$updateQueryPaywall .= "WHERE `paywall_id` IN ('" . implode( "', '", $IDs ) . "');\n";
				$query .= $updateQueryPaywall;
			}
			//Create and UPDATE statement for the global table if needed.
			if( !empty( $updateQueryGlobal ) ) {
				$updateQueryGlobal .= "\tSET ";
				$IDs = [];
				foreach( $tugfields as $field ) {
					$updateQueryGlobal .= "`$field` = CASE `url_id`\n";
					foreach( $tugValues as $value ) {
						if( isset( $value[$field] ) ) {
							$updateQueryGlobal .= "\t\tWHEN '{$value['url_id']}' THEN '{$value[$field]}'\n";
						} else $updateQueryGlobal .= "\t\tWHEN '{$value['url_id']}' THEN NULL\n";
						if( !in_array( $value['url_id'], $IDs ) ) $IDs[] = $value['url_id'];
					}
					$updateQueryGlobal .= "\tEND,\n\t";
				}
				$updateQueryGlobal = substr( $updateQueryGlobal, 0, strlen( $updateQueryGlobal ) - 7 ) . "\tEND\n";
				$updateQueryGlobal .= "WHERE `url_id` IN ('" . implode( "', '", $IDs ) . "');\n";
				$query .= $updateQueryGlobal;
			}
			//Create an UPDATE statement for the local table if needed.
			if( !empty( $updateQueryLocal ) ) {
				$updateQueryLocal .= "\tSET ";
				$IDs = [];
				foreach( $tulfields as $field ) {
					$updateQueryLocal .= "`$field` = CASE `url_id`\n";
					foreach( $tulValues as $value ) {
						if( isset( $value[$field] ) ) {
							$updateQueryLocal .= "\t\tWHEN '{$value['url_id']}' THEN '{$value[$field]}'\n";
						} else $updateQueryLocal .= "\t\tWHEN '{$value['url_id']}' THEN NULL\n";
						if( !in_array( $value['url_id'], $IDs ) ) $IDs[] = $value['url_id'];
					}
					$updateQueryLocal .= "\tEND,\n\t";
				}
				$updateQueryLocal = substr( $updateQueryLocal, 0, strlen( $updateQueryLocal ) - 7 ) . "\tEND\n";
				$updateQueryLocal .= "WHERE `url_id` IN ('" . implode( "', '", $IDs ) .
				                     "') AND `pageid` = '{$this->commObject->pageid}';\n";
				$query .= $updateQueryLocal;
			}
		}
		//Check for unused entries in the local table.
		if( !empty( $this->cachedPageResults ) ) {
			$urls = [];
			foreach( $this->cachedPageResults as $id => $values ) {
				$values = $this->sanitizeValues( $values );
				if( !isset( $values['nodelete'] ) ) {
					$urls[] = $values['url_id'];
				}
			}
			//Create a DELETE statement deleting those unused entries.
			if( !empty( $urls ) ) {
				$deleteQuery .= "DELETE FROM `externallinks_" . WIKIPEDIA . "` WHERE `url_id` IN ('" .
				                implode( "', '", $urls ) .
				                "') AND `pageid` = '{$this->commObject->pageid}'; ";
			}
			$query .= $deleteQuery;
		}
		//Run all queries asynchronously.  Best performance.  A maximum of 7 queries are executed simultaneously.
		if( $query !== "" ) {
			$res = self::queryMulti( $query );
			if( $res === false ) {
				echo "ERROR: " . mysqli_errno( self::$db ) . ": " . mysqli_error( self::$db ) . "\n";
			}
			while( mysqli_more_results( self::$db ) ) {
				$res = mysqli_next_result( self::$db );
				if( $res === false ) {
					echo "ERROR: " . mysqli_errno( self::$db ) . ": " . mysqli_error( self::$db ) . "\n";
				}
			}
		}
	}

	/**
	 * Flags all dbValues that have changed since they were stored
	 *
	 * @access    public
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function checkForUpdatedValues() {
		//This function uses the odbValues that were set in the retrieveDBValues function.
		foreach( $this->dbValues as $tid => $values ) {
			foreach( $values as $id => $value ) {
				if( $id == "url_id" || $id == "paywall_id" ) continue;
				if( !array_key_exists( $id, $this->odbValues[$tid] ) || $this->odbValues[$tid][$id] != $value ) {
					switch( $id ) {
						case "notified":
							if( !isset( $this->dbValues[$tid]['createlocal'] ) ) {
								$this->dbValues[$tid]['updatelocal'] =
									true;
							}
							break;
						case "paywall_status":
							if( !isset( $this->dbValues[$tid]['createpaywall'] ) ) {
								$this->dbValues[$tid]['updatepaywall'] = true;
							}
							break;
						default:
							if( !isset( $this->dbValues[$tid]['createglobal'] ) ) {
								$this->dbValues[$tid]['updateglobal'] = true;
							}
							break;
					}
				}
			}
		}
	}

	/**
	 * mysqli escape an array of values including the keys
	 *
	 * @param array $values Values of the mysqli query
	 *
	 * @access    protected
	 * @return array Sanitized values
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	protected function sanitizeValues( $values ) {
		$returnArray = [];
		foreach( $values as $id => $value ) {
			if( !is_null( $value ) && ( $id != "access_time" && $id != "archive_time" && $id != "last_deadCheck" ) ) {
				$returnArray[mysqli_escape_string( self::$db, $id )] = mysqli_escape_string( self::$db, $value );
			} elseif( !is_null( $value ) ) {
				$returnArray[mysqli_escape_string( self::$db, $id )] =
					( $value != 0 ? mysqli_escape_string( self::$db, date( 'Y-m-d H:i:s', $value ) ) : null );
			}
		}

		return $returnArray;
	}

	/**
	 * Multi run the given SQL unless in test mode
	 *
	 * @access private
	 * @static
	 *
	 * @param string $query the query
	 *
	 * @return mixed The result
	 */
	private static function queryMulti( $query ) {
		return self::query( $query, true );
	}

	/**
	 * Sets the notification status to notified
	 *
	 * @param mixed $tid $dbValues index to modify
	 *
	 * @access    public
	 * @return bool True on success, false on failure/already set
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function setNotified( $tid ) {
		if( isset( $this->dbValues[$tid] ) ) {
			if( isset( $this->dbValues[$tid]['notified'] ) && $this->dbValues[$tid]['notified'] == 1 ) return false;
			if( API::isEnabled() && DISABLEEDITS === false ) $this->dbValues[$tid]['notified'] = 1;

			return true;
		} elseif( isset( $this->dbValues[( $tid = ( explode( ":", $tid )[0] ) )] ) ) {
			if( isset( $this->dbValues[$tid]['notified'] ) && $this->dbValues[$tid]['notified'] == 1 ) return false;
			if( API::isEnabled() && DISABLEEDITS === false ) $this->dbValues[$tid]['notified'] = 1;

			return true;
		} else return false;
	}

	/**
	 * Retrieves specific information regarding a link and stores it in self::dbValues
	 * Attempts to retrieve it from cache first
	 *
	 * @param string $link URL to fetch info about
	 * @param int $tid Key ID to preserve array keys
	 *
	 * @access    public
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function retrieveDBValues( $link, $tid ) {
		//Fetch the values from the cache, if possible.
		foreach( $this->cachedPageResults as $i => $value ) {
			if( strtolower( $value['url'] ) == strtolower( $link['url'] ) ) {
				$this->dbValues[$tid] = $value;
				$this->cachedPageResults[$i]['nodelete'] = true;
				if( isset( $this->dbValues[$tid]['nodelete'] ) ) unset( $this->dbValues[$tid]['nodelete'] );
				break;
			}
		}

		//If they don't exist in the cache...
		if( !isset( $this->dbValues[$tid] ) ) {
			$res =
				self::query( "SELECT externallinks_global.url_id, externallinks_global.paywall_id, url, archive_url, has_archive, live_state, unix_timestamp(last_deadCheck) AS last_deadCheck, archivable, archived, archive_failure, unix_timestamp(access_time) AS access_time, unix_timestamp(archive_time) AS archive_time, paywall_status, reviewed FROM externallinks_global LEFT JOIN externallinks_paywall ON externallinks_global.paywall_id = externallinks_paywall.paywall_id WHERE `url` = '" .
				             mysqli_escape_string( self::$db, $link['url'] ) . "';"
				);
			if( mysqli_num_rows( $res ) > 0 ) {
				//Set flag to create a local entry if the global entry exists.
				$this->dbValues[$tid] = mysqli_fetch_assoc( $res );
				$this->dbValues[$tid]['createlocal'] = true;
			} else {
				//Otherwise...
				mysqli_free_result( $res );
				$res = self::query( "SELECT paywall_id, paywall_status FROM externallinks_paywall WHERE `domain` = '" .
				                    mysqli_escape_string( self::$db, parse_url( $link['url'], PHP_URL_HOST ) ) . "';"
				);
				if( mysqli_num_rows( $res ) > 0 ) {
					//Set both flags to create both a local and a global entry if the paywall exists.
					$this->dbValues[$tid] = mysqli_fetch_assoc( $res );
					$this->dbValues[$tid]['createlocal'] = true;
					$this->dbValues[$tid]['createglobal'] = true;
				} else {
					//Otherwise, set all 3 flags to create an entry in all 3 tables, if non-exist.
					$this->dbValues[$tid]['createpaywall'] = true;
					$this->dbValues[$tid]['createlocal'] = true;
					$this->dbValues[$tid]['createglobal'] = true;
					$this->dbValues[$tid]['paywall_status'] = 0;
				}
				//Also create some variables for the global entry, and for use later.
				$this->dbValues[$tid]['url'] = $link['url'];
				//If there is an archive found in the given $link array, and the invalid_archive flag isn't set, store archive information.
				if( $link['has_archive'] === true && !isset( $link['invalid_archive'] ) ) {
					$this->dbValues[$tid]['archivable'] =
					$this->dbValues[$tid]['archived'] = $this->dbValues[$tid]['has_archive'] = 1;
					$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
					$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
					$this->dbValues[$tid]['archivable'] = 1;
					$this->dbValues[$tid]['archived'] = 1;
					$this->dbValues[$tid]['has_archive'] = 1;
				}
				//Some more defaults
				$this->dbValues[$tid]['last_deadCheck'] = 0;
				$this->dbValues[$tid]['live_state'] = 4;
			}
			mysqli_free_result( $res );
		}

		//This saves a copy of the current DB values state, for later comparison.
		$this->odbValues[$tid] = $this->dbValues[$tid];

		//If the link has been reviewed, lock the DB entry, otherwise, allow overwrites
		//Also invalid archives will not overwrite existing information.
		if( !isset( $this->dbValues[$tid]['reviewed'] ) || $this->dbValues[$tid]['reviewed'] == 0 ||
		    isset( $link['convert_archive_url'] )
		) {
			if( $link['has_archive'] === true &&
			    ( !isset( $link['invalid_archive'] ) || isset( $link['convert_archive_url'] ) ) &&
			    ( empty( $this->dbValues[$tid]['archive_url'] ) ||
			      $link['archive_url'] != $this->dbValues[$tid]['archive_url'] )
			) {
				$this->dbValues[$tid]['archive_url'] = $link['archive_url'];
				$this->dbValues[$tid]['archive_time'] = $link['archive_time'];
				$this->dbValues[$tid]['archivable'] = 1;
				$this->dbValues[$tid]['archived'] = 1;
				$this->dbValues[$tid]['has_archive'] = 1;
			}
		}
		//Validate existing DB archive
		$temp = [];
		if( isset( $this->dbValues[$tid]['has_archive'] ) && $this->dbValues[$tid]['has_archive'] == 1 &&
		    API::isArchive( $this->dbValues[$tid]['archive_url'], $temp ) &&
		    !isset( $temp['archive_partially_validated'] ) && !isset( $temp['invalid_archive'] )
		) {
			if( isset( $temp['convert_archive_url'] ) ) {
				$this->dbValues[$tid]['archive_url'] = $temp['archive_url'];
				$this->dbValues[$tid]['archive_time'] = $temp['archive_time'];
			}
		} elseif( isset( $this->dbValues[$tid]['has_archive'] ) && $this->dbValues[$tid]['has_archive'] == 1 ) {
			$this->dbValues[$tid]['has_archive'] = 0;
			$this->dbValues[$tid]['archive_url'] = null;
			$this->dbValues[$tid]['archive_time'] = null;
			$this->dbValues[$tid]['archived'] = 2;
		}
		//Flag the domain as a paywall if the paywall tag is found
		if( $link['tagged_paywall'] === true ) {
			if( isset( $this->dbValues[$tid]['paywall_status'] ) && $this->dbValues[$tid]['paywall_status'] == 0 ) {
				$this->dbValues[$tid]['paywall_status'] = 1;
			}
		}
	}

	/**
	 * close the DB handle
	 *
	 * @access    public
	 * @return void
	 * @license   https://www.gnu.org/licenses/agpl-3.0.txt
	 * @copyright Copyright (c) 2015-2023, Maximilian Doerr, Internet Archive
	 * @author    Maximilian Doerr (Cyberpower678)
	 */
	public function closeResource() {
		$this->commObject = null;
	}
}