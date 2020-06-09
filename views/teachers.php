<h1>Teachers</h1>

<?php
foreach ($faculty as $f) {
	if (isset($tid) && $tid && $tid != $f['id']) {
		continue; // if we got passed an ID, only show that one
	}	
echo "<div class=\"row border-top p-3 m-3\">\n";
if ($src = \Teachers\get_teacher_photo_src($f['user_id'])) {
	echo "<div class=\"col-sm-2 p-2\"><img class=\"img-fluid\" src=\"$src\"></div>\n";
}
echo "	<div class=\"col-sm-10 p-2\">\n";
echo "		<h2>{$f['nice_name']}</h2>\n";
echo "<p>".preg_replace('/\R/', "<br>", $f['bio'])."</p>\n";


if (count($f['classes']) > 0) {
	echo "<p>Upcoming classes for {$f['nice_name']}:<ul>\n";
	foreach ($f['classes'] as $c) {
		echo "	<li><a href=\"workshop.php?wid={$c['id']}\">{$c['title']}</a></li>\n";
	}
	echo "</ul></p>\n";
}

echo "	</div>\n"; // end of col
echo "</div>\n"; // end of row
}