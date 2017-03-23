<?php
error_reporting(-1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo json_encode(linksCount(
	isset($_REQUEST['namespace']) ? $_REQUEST['namespace'] : 0,
	isset($_REQUEST['p']) ? $_REQUEST['p'] : '',
	isset($_REQUEST['fromNamespace']) ? $_REQUEST['fromNamespace'] : '',
	isset($_REQUEST['invertFromNamespace']) ? $_REQUEST['invertFromNamespace'] === 'true' : false,
	isset($_REQUEST['dbname']) ? $_REQUEST['dbname'] : 'fawiki'
));

function linksCount($namespace, $page, $fromNamespace, $invertFromNamespace, $dbname) {
	if ($page === '') {
		return ['#documentation' => 'Page links and transclusions count retrieval, use it like ?namespace=0&p=Earth&fromNamespace=0&dbname=enwiki Source: github.com/ebraminio/linkscount'];
	}

	if (preg_match('/^[a-z_\-]{1,20}$/', $dbname) === 0) { return ['#error' => 'Invalid "dbname" is provided']; };
	if (preg_match('/wiki$/', $dbname) === 0) { $dbname = $dbname . 'wiki'; }

	$ini = parse_ini_file('../replica.my.cnf');
	$db = mysqli_connect('enwiki.labsdb', $ini['user'], $ini['password'], $dbname . '_p');

	$namespace = +$namespace;
	$page = mysqli_real_escape_string($db, str_replace(' ', '_', $page));

	$plExtraCondition = '';
	$tlExtraCondition = '';
	if ($fromNamespace !== '') {
		$fromNamespace = +$fromNamespace;
		$operator = $invertFromNamespace ? '<>' : '=';
		$plExtraCondition = "AND pl_from_namespace $operator $fromNamespace";
		$tlExtraCondition = "AND tl_from_namespace $operator $fromNamespace";
	}

	$pagelinks = execCountQuery($db, "
		SELECT COUNT(*)
		FROM pagelinks
		WHERE pl_namespace = $namespace AND pl_title = '$page' $plExtraCondition;
	");
	if ($pagelinks === -1) { return ['#error' => 'Internal server error']; }

	$templatelinks = execCountQuery($db, "
		SELECT COUNT(*)
		FROM templatelinks
		WHERE tl_namespace = $namespace AND tl_title = '$page' $tlExtraCondition;
	");
	if ($templatelinks === -1) { return ['#error' => 'Internal server error']; }

	mysqli_close($db);

	return ['pagelinks' => $pagelinks, 'templatelinks' => $templatelinks];
}

function execCountQuery($db, $query) {
	$dbResult = mysqli_query($db, $query);
	if (!$dbResult) {
		error_log(mysqli_error($db));
		error_log($query);
		return -1;
	}
	$count = +($dbResult->fetch_row()[0]);
	mysqli_free_result($dbResult);
	return $count;
}