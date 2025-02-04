<?php

require_once( 'html/loader.php' );
ini_set( 'memory_limit', '8G' );

if( !empty( $offloadDBs ) ) {
	$dbObject = new DB2();

	foreach( $dbObject->getOffloadedTables() as $table => $offloadCriteria ) {
		if( !isset( $offloadableTables[$table] ) ) {
			echo "ERROR: $table cannot be offloaded at this time.\n";
			continue;
		}

		$sqlFetch = "SELECT * FROM $table WHERE (";
		$needAnd = false;
		$needOr = false;
		$idColumnName = $offloadableTables[$table]['limit'][1];
		foreach( $offloadCriteria as $criterion => $value ) {
			if( !isset( $offloadableTables[$table][$criterion] ) ) {
				echo "WARN: $table-$criterion is not a valid criterion\n";
				continue;
			}
			if( $needOr ) $sqlFetch .= " OR ";
			$keywords = [
				'(__TIMESTAMP__ - __VALUE__)' => date( 'Y-m-d', strtotime( "-$value" ) ),
				'__TIMESTAMP__'               => date( 'Y-m-d' ),
				'__VALUE__'                   => $value
			];
			switch( $criterion ) {
				case 'limit':
					$sqlFetch .= "'$idColumnName' " .
					             str_replace( array_keys( $keywords ), $keywords, $offloadableTables[$table]['limit'][0]
					             );
					break;
				default:
					$sqlFetch .= "'$criterion' " .
					             str_replace( array_keys( $keywords ), $keywords, $offloadableTables[$table][$criterion]
					             );
			}
			$needOr = true;
		}

		$sqlFetch .= ")";

		if( $sqlFetch == "SELECT * FROM $table WHERE ()" ) {
			echo "WARN: $table is designated to be offloaded, but no valid criteria for offloaded specified.\n";
			continue;
		}
		if( count( $offloadableTables[$table]['__RESTRICTIONS__'] ) > 1 ) {
			$sqlFetch .= " AND (";
			foreach( $offloadableTables[$table]['__RESTRICTIONS__'] as $column => $restriction ) {
				if( in_array( $column, 'fast_offload' ) ) continue;
				if( $needAnd ) $sqlFetch .= " AND ";
				$sqlFetch .= "'$column' $restriction";
				$needAnd = true;
			}
			$sqlFetch .= ")";
		}
		$sqlFetch .= " LIMIT 50000;";

		$fastOffload = $offloadableTables[$table]['__RESTRICTIONS__'];

		do {
			$res = $dbObject->queryDB( $sqlFetch, true );
			$rows = [];
			$ids = [];
			$numRows = $res->num_rows();

			while( $result = $res->fetch_assoc() ) {
				$rows[] = $result;
				$ids[] = $result[$idColumnName];
			}

			$maxID = max( $ids );

			if( !$dbObject->offloadRows( $rows, $table, $error ) ) {
				echo "ERROR: Offloading '$table' has failed, for a chunk of data, no data was removed from production\n";
				echo "Removing data chunk being offload from offload databases...\n";
				echo "SQL ERROR {$error['code']}: {$error['message']}\n";
				$error = false;

				if( !$dbObject->deleteOffloadedRows( $ids, $idColumnName, $table, $error ) ) {
					echo "ERROR: Unable to purge data chunk that was added to the offloaded databases\n";
					echo "SQL ERROR {$error['code']}: {$error['message']}\n";
					$error = false;
				}
			} else {
				echo "Offloading '$table' chunk succeeded, removing data from production...\n";

				if( !$fastOffload ) $sql =
					"DELETE FROM $table WHERE `$idColumnName` IN (" . implode( ',', $ids ) . ");";
				else $sql = "DELETE FROM $table WHERE `$idColumnName` < $maxID;";

				if( !$dbObject->queryDB( $sql, true ) ) {
					echo "ERROR: Unable to purge offloaded data from '$table' on production\n";
					echo "SQL ERROR " . $dbObject->getError() . ": " . $dbObject->getError( true ) . "\n";
				}
			}

			if( $numRows > 0 ) echo "More data from '$table' needs to be offloaded, probably, let's do it again.\n";
		} while( $numRows > 0 );
	}
}