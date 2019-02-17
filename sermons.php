<?php
mysqli_report(MYSQLI_REPORT_ERROR);
require_once('books.php');
$testing = true;
$encoding = 'UTF-8';
require_once('getid3/getid3/getid3.php');
require_once('getid3/getid3/write.php');
$getID3 = new getID3;
$getID3->setOption(array('encoding'=>$encoding));

$server_name = "127.0.0.1";
$username = "boisecc1_website";
$password = "website12834";
$db_name = "boisecc1_joomla";
$prefix = 'vqyis_';
$pptx_settings = '|width:650|height:450|border:1|border_style:solid|border_color:#000000';
$docx_settings = '|width:600|height:800|border:1|border_style:solid|border_color:#000000';
$conn = null;
$filename = null;
$sermon_dir = null;

function makeSermon($message_mp3, $message_ppt, $message_docx, $message_image, $title_english, $title_chinese, $date, $catid, $series, $speaker, $scripture, $scriptures, $image_verse)
{
    global $conn,
           $server_name,
           $username,
           $password,
           $db_name,
           $encoding,
           $filename,
           $prefix,
           $pptx_settings,
           $docx_settings,
           $sermon_dir,
           $getID3;

    if (!$speaker || !$series || !$catid || !$date || !$title_english || !$title_chinese)
        die('Invalid data!');

    $filename = date('Y-m-d', strtotime($date)) . '_' . trim(preg_replace('/[^A-Za-z0-9_-]/', '-', $title_english)) . '_BCCC';

    if ($catid !== 21) {
        $sermon_dir = 'sermonspeaker/sermons';
    } else {
        $sermon_dir = 'sermonspeaker/friday';
    }

    if (!file_exists($sermon_dir)) {
        mkdir('../' . $sermon_dir, 0777, true);
    }
    chdir('../' . $sermon_dir);

    // Create connection
    $conn = new mysqli($server_name, $username, $password, $db_name);
    $conn->set_charset('utf8');
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $sql = "SELECT * FROM " . $prefix . "sermon_sermons WHERE audiofile LIKE '/" . $sermon_dir . "/" . $date . "_%_BCCC.mp3'";
    $result = $conn->query($sql);
    $existing_row = mysqli_fetch_assoc($result);
    $old_message_mp3 = null;
    $old_message_ppt = null;
    $old_message_docx = null;
    $old_message_image = null;

    if ($existing_row) {
        echo "This date (".$date.") already has a sermon that exists, so updating it...\n";
//        echo "HAVE ROW:\n";
//        var_dump($existing_row);
        $time = time();
        $file = pathinfo($existing_row['audiofile'])['filename'];
        if (file_exists($file . '.mp3')) {
            $old_message_mp3 = $file . '_OLD-' . $time . '.mp3';
            rename($file . '.mp3', $old_message_mp3);
        }
        if (file_exists($file . '.pptx')) {
            $old_message_ppt = $file . '_OLD-' . $time . '.pptx';
            rename($file . '.pptx', $old_message_ppt);
        }
        if (file_exists($file . '.docx')) {
            $old_message_docx = $file . '_OLD-' . $time . '.docx';
            rename($file . '.docx', $old_message_docx);
        }
        if (file_exists($file . '.jpg')) {
            $old_message_image = $file . '_OLD-' . $time . '.jpg';
            rename($file . '.jpg', $old_message_image);
        }
    } else {
        echo $filename . ".mp3 is new!<br/>\n\n";
    }

    if (!$message_mp3 || !file_exists($message_mp3)) {
        $message_mp3 = ($old_message_mp3 ? $old_message_mp3 : '../../no_recording.mp3');
    }

    if ($message_mp3) {
        $ret = copy($message_mp3, $filename . '.mp3');
        $message_mp3 = $filename . '.mp3';
    }

    if (!$message_ppt || !file_exists($message_ppt)) {
        $message_ppt = ($old_message_ppt ? $old_message_ppt : null);
    }
    if ($message_ppt) {
        copy($message_ppt, $filename . '.pptx');
        $message_ppt = $filename . '.pptx';
    }

    if (!$message_docx || !file_exists($message_docx)) {
        $message_docx = ($old_message_docx ? $old_message_docx : null);
    }
    if ($message_docx) {
        copy($message_docx, $filename . '.docx');
        $message_docx = $filename . '.docx';
    }

    if ((!$message_image || !file_exists($message_image)) && $image_verse == null) {
        $message_image = ($old_message_image ? $old_message_image : null);
    }
    if ($message_image) {
        copy($message_image, $filename . '.jpg');
        $message_image = $filename . '.jpg';
    }

    if($old_message_mp3) {
        $info = $getID3->analyze($old_message_mp3);
        if (! $series)
            $series = $info['tags']['id3v2']['album'][0];
        if (! $speaker)
            $speaker = $info['tags']['id3v2']['artist'][0];
        if (! $scriptures)
            $scriptures = $info['tags']['id3v2']['comment'][0];
        if (! $title_english)
            $title_english = $info['tags']['id3v2']['title'][0];
    }

    $tagwriter = new getid3_writetags;
    $tagwriter->filename = $message_mp3;
    $tagwriter->tagformats = array('id3v1', 'id3v2.3');
    $tagwriter->overwrite_tags = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
    $tagwriter->remove_other_tags = false; // if true removes other tag formats (e.g. ID3v1, ID3v2, APE, Lyrics3, etc) that may be present in the file and only write the specified tag format(s). If false leaves any unspecified tag formats as-is.
    $tagwriter->tag_encoding = $encoding;

    $scriptures = preg_replace('/  +/', ' ', $scriptures); # Removes any double spaces
    if(! $scriptures || strpos($scriptures, $scripture) === false) {
        $scriptures = $scripture.($scriptures?"\n".$scriptures:"");
    }

    $tag_data = array(
        'title' => array($title_english . ' ' . $title_chinese . ' - ' . $date),
        'artist' => array($speaker),
        'album' => array($series),
        'year' => array(date('Y', strtotime($date))),
        'genre' => array('Sermons'),
        'comment' => array($scriptures),
        'track' => array('01'),
        'popularimeter' => array('email' => 'info@boiseccc.org', 'rating' => 128, 'data' => 0),
        'unique_file_identifier' => array('ownerid' => 'info@boiseccc.org', 'data' => md5(time())),
    );
    echo "Updating tags in MP3 file... (see <a href=\"../$sermon_dir/$filename-tags.txt\" target=\"_blank\">tag file</a>)\n";
//    var_dump($tag_data);
    file_put_contents($filename . "-tags.txt", json_encode($tag_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $tagwriter->tag_data = $tag_data;

    if ($tagwriter->WriteTags()) {
        echo 'Successfully wrote tags<br>';
        if (!empty($tagwriter->warnings)) {
            echo 'There were some warnings:<br>' . implode('<br><br>', $tagwriter->warnings);
        }
    } else {
        die('Failed to write tags!<br>' . implode('<br><br>', $tagwriter->errors));
    }

    if (! $scripture && $scriptures)
        $scripture = explode("\n", $scriptures)[0];
    $scripture = trim($scripture);
    $series_id = makeSeries($series, $catid);
    $speaker_id = makeSpeaker($speaker, $catid);
    if (!$message_image || ! file_exists($message_image)) {
        if (!$image_verse)
            $image_verse = $scripture;
        $image_verse = trim(preg_replace('/[^A-Za-z0-9 _:,-]/', '', $image_verse)); # removes chinese characters
        $image_verse = preg_replace('/  +/', ' ', $image_verse); # Removes any double spaces
        $message_image = $filename . '.jpg';
    }

    $body_lines = array('<p>經文 Scripture:<ul><li>'.preg_replace('/ *\n */', '</li><li>', $scriptures).'</li></ul></p>');
    $add_file = '';
    $add_file_desc = '';
    if (file_exists($message_ppt)) {
        $body_lines[] = '{google_docs}/' . $sermon_dir . '/' . basename($message_ppt) . $pptx_settings . '{/google_docs}';
        $add_file = '/' . $sermon_dir . '/' . basename($message_ppt);
        $add_file_desc = 'PowerPoint Slides';
    }
    if (file_exists($message_docx)) {
        $body_lines[] = '{google_docs}/' . $sermon_dir . '/' . basename($message_docx) . $docx_settings . '{/google_docs}';
        if (!$add_file) {
            $add_file = '/' . $sermon_dir . '/' . basename($message_docx);
            $add_file_desc = 'Lesson Notes';
        }
    }
    $picture = '';
//    echo "CHECKING $message_image for $image_verse\n";
    if (!file_exists($message_image) && $image_verse) {
//        echo "MAKING $message_image for $image_verse\n";
        makeImage($message_image, $image_verse);
    }
    if (file_exists($message_image)) {
        $picture = $sermon_dir . '/' . basename($message_image);
    }
    $body = implode("<br/>\n", $body_lines);
    $audio_file = '/' . $sermon_dir . '/' . basename($message_mp3);
    $audio_file_size = filesize($message_mp3);

    $info = $getID3->analyze($message_mp3);
    $sermon_time = format_duration($info['playtime_string']);
    $title = $title_english . ' ' . $title_chinese;
    $alias = strtolower($filename);
    $creation_date = date("Y-m-d H:i:s");

    $row = array(
        'speaker_id' => $speaker_id,
        'series_id' => $series_id,
        'audiofile' => $audio_file,
        'videofile' => '',
        'audiofilesize' => $audio_file_size,
        'picture' => $picture,
        'title' => $title,
        'alias' => $alias,
        'sermon_number' => 1,
        'sermon_date' => $date . ' 10:30:00',
        'sermon_time' => $sermon_time,
        'notes' => $body,
        'state' => 1,
        'created' => $creation_date,
        'created_by' => 648,
        'podcast' => 1,
        'catid' => $catid,
        'addfile' => $add_file,
        'addfileDesc' => $add_file_desc,
    );

    if ($existing_row) {
        $sermon_id = $existing_row['id'];
        updateTable($prefix . 'sermon_sermons', $row, $sermon_id);
        echo "Updating Sermon... ";
    } else {
        $sermon_id = insertIntoTable($prefix . 'sermon_sermons', $row);
        echo "Adding Sermon... ";
    }
    echo "(see <a href=\"../$sermon_dir/$filename-row.txt\" target=\"_blank\"'>row data</a>\n";
    deleteScriptures($sermon_id);
    makeScriptureRef($sermon_id, $scripture);
    $row['id'] = $sermon_id;
//    var_dump($row);
    file_put_contents($filename . "-row.txt", json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "DONE: series: $series_id, speaker: $speaker_id, sermon: $sermon_id<br/>\n\n";

    $conn->close();
}

// Format to AA::BB:CC
function format_duration($duration)
{
	// The base case is A:BB
	if (strlen($duration) == 4) {
		return "00:0" . $duration;
	} // If AA:BB
	else if (strlen($duration) == 5) {
		return "00:" . $duration;
	}   // If A:BB:CC
	else if (strlen($duration) == 7) {
		return "0" . $duration;
	}
	return "00:00";
}

function makeSeries($title, $catid)
{
	global $prefix, $conn;
	$sql = "SELECT * from " . $prefix . "sermon_series WHERE title = \"" . $title . "\"";
//	echo $sql . "<br/>\n\n";
	$result = $conn->query($sql);
	$row = mysqli_fetch_assoc($result);
	if ($row) {
		return $row['id'];
	} else {
		$row = array(
			'title' => $title,
			'alias' => strtolower(str_replace(' ', '-', trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $title)))),
			'state' => 1,
			'created' => date('Y-m-d H:i:s'),
			'created_by' => 648,
			'catid' => $catid,
			'metadata' => '{"robots":"","rights":""}',
		);
		return insertIntoTable($prefix . 'sermon_series', $row);
	}
}

function makeSpeaker($title, $catid)
{
	global $prefix, $conn;
	$sql = "SELECT * from " . $prefix . "sermon_speakers WHERE title = \"" . $title . "\"";
//	echo $sql . "<br/>\n\n";
	$result = $conn->query($sql);
	$row = mysqli_fetch_assoc($result);
	if ($row) {
		return $row['id'];
	} else {
		$row = array(
			'title' => $title,
			'alias' => strtolower(str_replace(' ', '-', trim(preg_replace('/[^A-Za-z0-9 _-]/', '', $title)))),
			'website' => 'http://www.boiseccc.org',
			'pic' => 'images/speakers/blank-profile-picture.jpg',
			'intro' => '',
			'bio' => '',
			'state' => 1,
			'created' => date('Y-m-d H:i:s'),
			'created_by' => 648,
			'catid' => $catid,
			'metadata' => '{"robots":"","rights":""}',
		);
		return insertIntoTable($prefix . 'sermon_speakers', $row);
	}
}

function insertIntoTable($table, $row)
{
	global $conn;
	$fields = array();
	$values = array();
	foreach ($row as $field => $value) {
		$fields[] = $field;
		$values[] = mysqli_real_escape_string($conn, $value);
	}
	$sql = 'INSERT INTO `' . $table . '` (' . implode($fields, ',') . ') VALUES ("' . implode($values, '","') . '")';
//	echo $sql . "<br/>\n\n";
	$conn->query($sql);
//	echo $conn->insert_id . "<br/>\n\n";
	return $conn->insert_id;
}

function updateTable($table, $row, $id) {
    global $conn;
    $sets = array();
    foreach ($row as $field => $value) {
        $sets[] = "$field = \"".mysqli_real_escape_string($conn, $value)."\"";
    }
    $sql = 'UPDATE `' . $table . '` SET '.implode(',', $sets).' WHERE id = '.$id;
//    echo $sql . "<br/>\n\n";
    $conn->query($sql);
    return $id;
}

function deleteScriptures($id) {
    global $conn, $prefix;
    $sql = 'DELETE FROM `' . $prefix . 'sermon_scriptures` WHERE sermon_id = '.$id;
//    echo $sql . "<br/>\n\n";
    $conn->query($sql);
    return $id;
}

function makeScriptureRef($sermon_id, $scripture)
{
	global $prefix, $filename, $sermon_dir;
	$scriptures = explode("\n", $scripture);
	$refs = array();
	$bad_refs = array();
	$order = array();
	foreach($scriptures as $script) {
	    $script = preg_replace('/[^A-Za-z0-9 :,_-]/', '', $script);
	    $script = trim($script);
	    if (strpos($script, ':') === false)
    	    $script = preg_replace('/(.+)(\d+) +(\d+)/', '\1\2:\3', $script);
        $ref = getScriptureRef($script);
        if ($ref && $ref['book']) {
            $ref['sermon_id'] = $sermon_id;
            if (! isset($refs[$script])) {
                $refs[$script] = $ref;
                $order[] = $script;
            }
        } else {
            $bad_refs[] = $script;
        }
    }
//    print("ORDER:");
//    var_dump($order);
//    print("REFS:");
//    var_dump($refs);
//    print("BAD:");
//    var_dump($bad_refs);
    foreach($order as $script) {
        insertIntoTable($prefix . 'sermon_scriptures', $refs[$script]);
    }
    echo "Adding Scripture References... (see <a href=\"../$sermon_dir/$filename-refs.txt\" target=\"_blank\">scripture refs</a>)\n";
    file_put_contents($filename . "-refs.txt", json_encode($refs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if (count($bad_refs)) {
        echo "Has bad Scripture References! (see <a href=\"../$sermon_dir/$filename-bad_refs.txt\" target=\"_blank\">bad refs</a>)\n";
        file_put_contents($filename . "-bad_refs.txt", json_encode($bad_refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

function getScriptureRef($str)
{
	$ref = array(
		'book' => 0,
		'cap1' => 0,
		'vers1' => 0,
		'cap2' => 0,
		'vers2' => 0,
	);

//	echo "STR: $str\n";
	# Matthew 5
	preg_match('/^([^:-]+) (\d+)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		return $ref;
	}

	# Matthew 5-6
	preg_match('/^([^:-]+) (\d+)-(\d+)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['cap2'] = $matches[3];
		return $ref;
	}

	# Matthew 5:6
	preg_match('/^([^:-]+) (\d+):(\d[\dab]*)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['vers1'] = preg_replace('/[^\d]/', '', $matches[3]);
		return $ref;
	}

	# Matthew 5:1-6
	preg_match('/^([^:-]+) (\d+):(\d[\dab]*)-(\d[\dab]*)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['vers1'] = preg_replace('/[^\d]/', '', $matches[3]);
		$ref['vers2'] = preg_replace('/[^\d]/', '', $matches[4]);
		return $ref;
	}

	# Matthew 5-6:1
	preg_match('/^([^:-]+) (\d+)-(\d+):(\d[\dab]*)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['vers1'] = 1;
		$ref['cap2'] = $matches[3];
		$ref['vers2'] = preg_replace('/[^\d]/', '', $matches[4]);
		return $ref;
	}

	# Matthew 5:1-6:1
	preg_match('/^([^:-]+) (\d+):(\d[\dab]*)-(\d+):(\d[\dab]*)$/', $str, $matches);
	if ($matches) {
		print_r($matches);
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['vers1'] = preg_replace('/[^\d]/', '', $matches[3]);
		$ref['cap2'] = $matches[4];
		$ref['vers2'] = preg_replace('/[^\d]/', '', $matches[5]);
		return $ref;
	}

	# Matthew
	$ref['book'] = getBookNumber($str);
	if ($ref['book'])
		return $ref;
	else
		# Junk
		return null;
}

function getBookNumber($name)
{
	global $books;
	foreach ($books as $num => $names) {
		if (in_array($name, $names)) {
			return $num;
		}
	}
	return 0;
}

function makeImage($basename, $scripture)
{
	global $books;
	$scriptureRef = getScriptureRef($scripture);
//	print_r($scriptureRef);
	if (!$scriptureRef || !$scriptureRef['book'] || !$books[$scriptureRef['book']] || !$scriptureRef['cap1'])
		return null;
	$scripturePath = str_replace(' ', '_', strtolower($books[$scriptureRef['book']][0])) . '/' . $scriptureRef['cap1'] . '-' . ($scriptureRef['vers1'] ? $scriptureRef['vers1'] : 1);
	// LINUX:
//    $command = "wget -O $basename $(curl https://biblepic.com/$scripturePath.htm | lynx --dump -listonly -stdin | sed -r 's/^\s*[0-9]+\. file:\/\/localhost/https:\/\/biblepic.com/' | grep jpg)";
    // MAC:
    $command = "curl -o $basename $(curl https://biblepic.com/$scripturePath.htm | /usr/local/bin/lynx --dump -listonly -stdin | /usr/local/bin/gsed -r 's/^\s*[0-9]+\. file:\/\//https:\/\/biblepic.com/' | grep jpg)";
//	echo $command . "\n";
	exec($command . " 2>&1", $output, $ret);
//	var_dump($output);
//    var_dump($ret);
	if(!file_exists($basename)) {
	    die('NO '.$basename);
    }
	return "$basename.jpg";
}
