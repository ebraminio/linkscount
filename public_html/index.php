<?php
declare(strict_types=1);

error_reporting(-1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo json_encode(linksCount(
	filter_var($_REQUEST['namespace'] ?? '', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]),
	$_REQUEST['p'] ?? '',
	filter_var($_REQUEST['fromNamespace'] ?? '', FILTER_VALIDATE_INT, ['options' => ['default' => null, 'min_range' => 0]]),
	filter_var($_REQUEST['invertFromNamespace'] ?? '', FILTER_VALIDATE_BOOLEAN),
	$_REQUEST['dbname'] ?? 'enwiki'
));

function linksCount(int $namespace, string $page, ?int $fromNamespace, bool $invertFromNamespace, string $dbname): array {
	if ($page === '') {
		return ['#documentation' => 'Page links and transclusions count retrieval, use it like ?namespace=0&p=Earth&fromNamespace=0&dbname=enwiki Source: github.com/ebraminio/linkscount'];
	}

	try {
		return doLinksCount($namespace, $page, $fromNamespace, $invertFromNamespace, $dbname);
	} catch (LinkscountException $e) {
		return ['#error' => $e->getMessage()];
	}
}

function doLinksCount(int $namespace, string $page, ?int $fromNamespace, bool $invertFromNamespace, string $dbname): array {
	if (preg_match('/^[a-z_\-]{1,20}$/', $dbname) !== 1) { throw new LinkscountException('Invalid "dbname" is provided'); }
	if (preg_match('/wiki$/', $dbname) !== 1) { $dbname = $dbname . 'wiki'; }

	$db = getDbConnection($dbname);

	$page = mysqli_real_escape_string($db, str_replace(' ', '_', $page));

	$plExtraCondition = '';
	$tlExtraCondition = '';
	$ilExtraCondition = '';
	if ($fromNamespace !== null) {
		$operator = $invertFromNamespace ? '<>' : '=';
		$plExtraCondition = "AND pl_from_namespace $operator $fromNamespace";
		$tlExtraCondition = "AND tl_from_namespace $operator $fromNamespace";
		$ilExtraCondition = "AND il_from_namespace $operator $fromNamespace";
	}

	// ID of the page in the `linktarget` table (to be used in other queries, NOT a link count)
	// It may be null if there are no links of types that have been normalized (i.e. no template
	// links right now)
	$linktarget = execIntQuery($db, "
		SELECT lt_id
		FROM linktarget
		WHERE lt_namespace = $namespace AND lt_title = '$page';
	");

	// TODO will be broken by T299947
	$pagelinks = execIntQuery($db, "
		SELECT COUNT(*)
		FROM pagelinks
		WHERE pl_namespace = $namespace AND pl_title = '$page' $plExtraCondition;
	");

	$templatelinks = $linktarget !== null
		? execIntQuery($db, "
			SELECT COUNT(*)
			FROM templatelinks
			WHERE tl_target_id = $linktarget $tlExtraCondition;
		")
		: 0;

	$filelinks = 0;
	if ($namespace === 6) {
		// TODO will be broken by T299953
		$filelinks = execIntQuery($db, "
			SELECT COUNT(*)
			FROM imagelinks
			WHERE il_to = '$page' $ilExtraCondition;
		");
	}

	$globalfilelinks = 0;
	if ($namespace === 6) {
		$globalfilelinks = execIntQuery(getDbConnection('commonswiki'), "
			SELECT COUNT(*)
			FROM globalimagelinks
			WHERE gil_to = '$page';
		");
	}

	return ['pagelinks' => $pagelinks, 'templatelinks' => $templatelinks, 'filelinks' => $filelinks, 'globalfilelinks' => $globalfilelinks];
}

/** Get a DB connection pointing to a suitable server and with default DB set. */
function getDbConnection(string $dbname): mysqli {
	mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
	$ini = parse_ini_file('../replica.my.cnf');
	try {
		return new mysqli($dbname . '.labsdb', $ini['user'], $ini['password'], $dbname . '_p');
	} catch (mysqli_sql_exception $e) {
		error_log("Unable to connect to database '$dbname': $e");
		throw new LinkscountException('Internal server error');
	}
}

/** Get a single integer result from the DB. */
function execIntQuery(mysqli $db, string $query): ?int {
	$dbResult = $db->query($query);
	if (!$dbResult) {
		error_log($db->error);
		error_log($query);
		throw new LinkscountException('Internal server error');
	}
	$row = $dbResult->fetch_row();
	$dbResult->free();
	return is_array($row) ? (int)$row[0] : null;
}

/** An error that should be shown to the user. */
class LinkscountException extends Exception {}
