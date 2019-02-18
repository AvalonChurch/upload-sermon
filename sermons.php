<?php
mysqli_report(MYSQLI_REPORT_ERROR);
require_once('books.php');
require_once('docx.php');
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

function getEnglishTitle($title) {
    $title = preg_replace('/^201\d-\d+-\d+ */', '', $title);
    $title = preg_replace('/^([\x00-\x7F]+)[^\x00-\x7F].*/', '$1', $title);
    $title = trim(explode(' - ', $title)[0]);
    echo "ENGLISH: $title\n";
    return $title;
}

function getChineseTitle($title) {
    $english = getEnglishTitle($title);
    $title = preg_replace('/^201\d-\d+-\d+ */', '', $title);
    $title = str_replace($english, '', $title);
    $title = preg_replace('/^[\x00-\x7F]*([^\x00-\x7f]+.*)/', '$1', $title);
    $title = trim(explode(' - ', $title)[0]);
    echo "CHINESE: $title\n";
    return $title;
}

function getChineseTitleFromDocx($docx_file) {
    try {
        $docxText = RD_Text_Extraction::convert_to_text($docx_file);
        $lines = explode("\n", $docxText);
        if (getChineseTitle($lines[0]))
            return getChineseTitle($lines[0]);
        else
            return getChineseTitle($lines[1]);
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

function setSermonDir($catid) {
    global $sermon_dir;
    if ($catid !== "21") {
        $sermon_dir = 'sermonspeaker/sermons';
    } else {
        $sermon_dir = 'sermonspeaker/friday';
    }
    if (!file_exists('../' . $sermon_dir)) {
        mkdir('../' . $sermon_dir, 0777, true);
    }
    echo "SERMON DIR: $sermon_dir\n\n";
    $ret = chdir('../' . $sermon_dir);
}

function getFileTitle($title) {
    echo "Start FileTitle: $title\n";
    $title = explode(':', $title)[0];
    $title = explode(' - ', $title)[0];
    if(strlen($title) > 60 ) {
        $title = substr($title, 0, 60);
        if (substr($title, 60, 1) != ' ')
            $title = implode('-', array_slice(explode(' ', $title), 0, -1));
    }
    $title = trim(preg_replace('/[^A-Za-z0-9_-]/', '-', $title));
    $title = preg_replace('/-+/', '-', $title);
    $title = preg_replace('/-+$/', '', $title);
    echo "End FileTitle: $title\n";
    return $title;
}

function cleanUpScripture($scripture) {
    $scripture = preg_replace('/：/', ':', $scripture);
    $scripture = preg_replace('/，/', ',', $scripture);
    $scripture = preg_replace('/,/', ', ', $scripture);
    $scripture = preg_replace('/-/', '-', $scripture);
    $scripture = preg_replace('/: +/', ':', $scripture);
    $scripture = preg_replace('/；/', '; ', $scripture);
    $scripture = preg_replace('/([^A-Za-z0-9: ,;_【】\n-])([A-Za-z0-9])/', '$1 $2', $scripture);
    $scripture = preg_replace('/  +/', ' ', $scripture);
    $scripture = trim($scripture);
    if(! preg_match('/[A-Z]/', $scripture)) {
        $scripture = preg_replace_callback('/【([^0-9abc ,;-]+)(.*)】/', function ($matches) {
            global $chineseToEnglish;
            print_r($matches);
            if($matches && $chineseToEnglish[$matches[1]])
                return $matches[1].' '.$chineseToEnglish[$matches[1]].' '.trim(matches[2]);
            else
                return $matches[0];
        }, $scripture);
        echo "SPECIAL: $scripture\n";
    }
    return $scripture;
}

function makeSermon($date = null, $message_mp3 = null, $message_pptx = null, $message_docx = null, $message_image = null, $title_english = null, $title_chinese = null, $catid = null, $series = null, $speaker = null, $main_scripture = null, $image_verse = null)
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

    if (!$date) {
        $date_pattern = '/^(201\d-\d+-\d+).*/';
        if($message_mp3 && preg_match($date_pattern, $message_mp3))
            $date = preg_replace($date_pattern, '$1', $message_mp3);
        if(!$date) {
            echo "A date must be provided, and if nothing else, it must be an existing sermon.\n";
            return;
        }
    }

    // Create connection
    $conn = new mysqli($server_name, $username, $password, $db_name);
    $conn->set_charset('utf8');
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $sql = "SELECT * FROM " . $prefix . "sermon_sermons WHERE sermon_date LIKE '$date %'";
    $result = $conn->query($sql);
    $existing_row = mysqli_fetch_assoc($result);
    $old_message_mp3 = null;
    $old_message_pptx = null;
    $old_message_docx = null;
    $old_message_image = null;
    $docx_scriptures = "";
    $pptx_scriptures = "";

    if ($existing_row) {
        echo "This date (".$date.") already has a sermon that exists, so updating it...\n";

        if (!$catid) {
            $catid = $existing_row['catid'];
        }
        setSermonDir($catid);

        $file = pathinfo($existing_row['audiofile'])['filename'];
        $info = $getID3->analyze($file.'.mp3');
        if($info) {
            if (!$series)
                $series = $info['tags']['id3v2']['album'][0];
            if (!$speaker)
                $speaker = $info['tags']['id3v2']['artist'][0];
            $tag_title = $info['tags']['id3v2']['title'][0];
            if (!$title_english)
                $title_english = getEnglishTitle($tag_title);
            if (!$title_chinese)
                $title_chinese = getChineseTitle($tag_title);
        }
        if(!$title_english) {
            $title_english = getEnglishTitle($existing_row['title']);
        }
        if(!$title_chinese) {
            $title_chinese = getChineseTitle($existing_row['title']);
            if(!$title_chinese && file_exists($file.'.docx')) {
                $title_chinese = getChineseTitleFromDocx($file.'.docx');
            }
        }

        $filename = date('Y-m-d', strtotime($date)) . '_' . getFileTitle($title_english) . '_BCCC';

        $time = time();
        if (file_exists($file . '.mp3')) {
            $old_message_mp3 = 'bak/' . $file . '_OLD-' . $time . '.mp3';
            rename($file . '.mp3', $old_message_mp3);
            echo "rename($file . '.mp3', $old_message_mp3);";
            if (!$message_mp3)
                $message_mp3 = $old_message_mp3;
            echo "message_mp3 = $message_mp3\n";
        }

        if (file_exists($file . '.pptx')) {
            $old_message_pptx = 'bak/' . $file . '_OLD-' . $time . '.pptx';
            rename($file . '.pptx', $old_message_pptx);
            if (!$message_pptx)
                $message_pptx = $old_message_pptx;
        }
        if (file_exists($file . '.docx')) {
            $old_message_docx = 'bak/' . $file . '_OLD-' . $time . '.docx';
            rename($file . '.docx', $old_message_docx);
            if (!$message_docx)
                $message_docx = $old_message_docx;
        }
        if (file_exists($file . '.jpg')) {
            $old_message_image = 'bak/' . $file . '_OLD-' . $time . '.jpg';
            rename($file . '.jpg', $old_message_image);
            if (!$message_image && ! $image_verse)
                $message_image = $old_message_image;
        }

        if (!$message_mp3 || !file_exists($message_mp3))
            $message_mp3 = ($old_message_mp3 ? $old_message_mp3 : '../../no_recording.mp3');

        if ($message_mp3 && file_exists($message_mp3)) {
            echo "copy($message_mp3, $filename . '.mp3')\n";
            copy($message_mp3, $filename . '.mp3');
            $message_mp3 = $filename . '.mp3';
        }

        if (!$message_pptx || !file_exists($message_pptx)) {
            $message_pptx = ($old_message_pptx ? $old_message_pptx : null);
        }
        if ($message_pptx && file_exists($message_pptx)) {
            echo "copy($message_pptx, $filename . '.pptx')\n";
            copy($message_pptx, $filename . '.pptx');
            $message_pptx = $filename . '.pptx';
        }

        if (!$message_docx || !file_exists($message_docx) && $old_message_docx) {
            $message_docx = $old_message_docx;
        }
        if ($message_docx && file_exists($message_docx)) {
            echo "copy($message_docx, $filename . '.docx')\n";
            copy($message_docx, $filename . '.docx');
            $message_docx = $filename . '.docx';
        }

        if ((!$message_image || !file_exists($message_image)) && $image_verse == null) {
            $message_image = ($old_message_image ? $old_message_image : null);
        }
        if ($message_image && file_exists($message_image)) {
            echo "copy($message_image, $filename . '.image')\n";
            copy($message_image, $filename . '.jpg');
            $message_image = $filename . '.jpg';
        }

        if(! $speaker) {
            $sql = "SELECT * FROM " . $prefix . "sermon_speakers WHERE id = " . $existing_row['speaker_id'];
            $result = $conn->query($sql);
            $speaker_row = mysqli_fetch_assoc($result);
            if ($speaker_row) {
                $speaker = $speaker_row['title'];
                echo "FOUND SPEAKER: $speaker\n";
            }
        }
        if(! $series) {
            $sql = "SELECT * FROM " . $prefix . "sermon_series WHERE id = " . $existing_row['series_id'];
            $result = $conn->query($sql);
            $series_row = mysqli_fetch_assoc($result);
            if ($series_row) {
                $series = $series_row['title'];
                echo "FOUND SERIES: $series\n";
            }
        }
        if(! $catid)
            $catid = $existing_row['catid'];
    } else {
        echo $filename . ".mp3 is new!\n\n";

        if(!$speaker){
            $speaker = "Barnabas Feng"; // default
        }
        if(!$series) {
            $series = "Matthew"; // default
        }
        if(! $catid) {
            $catid = 19; // Sunday Sermon
        }
        setSermonDir($catid);

        if(!$title_english) {
            echo "An English Title must be provided for new sermons";
            return;
        }
        if(!$title_chinese && ! $message_docx) {
            echo "A Chinese Title must be provided for new sermons";
            return;
        }
    }

    $filename = date('Y-m-d', strtotime($date)) . '_' . getFileTitle($title_english) . '_BCCC';

    if (!$message_mp3 || !file_exists($message_mp3)) {
        $message_mp3 = ($old_message_mp3 ? $old_message_mp3 : '../../upload-sermon/no_recording.mp3');
    }

    if ($message_mp3) {
        copy($message_mp3, $filename . '.mp3');
        $message_mp3 = $filename . '.mp3';
    }

    if (!$message_pptx || !file_exists($message_pptx)) {
        $message_pptx = ($old_message_pptx ? $old_message_pptx : null);
    }
    if ($message_pptx) {
        copy($message_pptx, $filename . '.pptx');
        $message_pptx = $filename . '.pptx';
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

    if(!$title_english)
        $title_english = "Sermon";

    $tagwriter = new getid3_writetags;
    $tagwriter->filename = $message_mp3;
    $tagwriter->tagformats = array('id3v1', 'id3v2.3');
    $tagwriter->overwrite_tags = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
    $tagwriter->remove_other_tags = false; // if true removes other tag formats (e.g. ID3v1, ID3v2, APE, Lyrics3, etc) that may be present in the file and only write the specified tag format(s). If false leaves any unspecified tag formats as-is.
    $tagwriter->tag_encoding = $encoding;

    if($message_docx && file_exists($message_docx)) {
        try {
            $docxText = RD_Text_Extraction::convert_to_text($message_docx);
            preg_match_all('/【(.*?)】/', $docxText, $matches, PREG_PATTERN_ORDER);
            $docx_scriptures = implode("\n", $matches[0]);
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

    if($message_pptx && file_exists($message_pptx)) {
        try {
            $pptxText = RD_Text_Extraction::convert_to_text($message_pptx);
            preg_match_all('/【([^】]*[0-9]+[^】]*)】/', $pptxText, $matches, PREG_PATTERN_ORDER);
            $pptx_scriptures = implode("\n", $matches[0]);
            if(!$message_docx) {
                $docx_scriptures = $pptx_scriptures;
                $pptx_scriptures = "";
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

    $docx_scriptures = cleanUpScripture($docx_scriptures);
    $pptx_scriptures = cleanUpScripture($pptx_scriptures);

    if($main_scripture && ! $docx_scriptures) {
        $docx_scriptures = $main_scripture;
        if (strpos($docx_scriptures, "【") === false)
            $docx_scriptures = "【" . $docx_scriptures . "】";
    }

    $comment = "";
    if($docx_scriptures)
        $comment = "經文 Scripture:\n * ".preg_replace('/ *\n */', "\n * ", $docx_scriptures)."\n\n";
    if($message_docx && file_exists($message_docx)) {
        try {
            $comment .= "筆記 Notes:\n\n";
            $comment .= RD_Text_Extraction::convert_to_text($message_docx);
        } catch(Exception $e) {
            die($e->getMessage());
        }
    }

    $tag_data = array(
        'title' => array($title_english . ($title_chinese?' - ' . $title_chinese:'') . ' - ' . $date),
        'artist' => array($speaker),
        'album' => array($series),
        'year' => array(date('Y', strtotime($date))),
        'genre' => array('Sermons'),
        'comment' => array($comment),
        'track' => array('01'),
        'popularimeter' => array('email' => 'info@boiseccc.org', 'rating' => 128, 'data' => 0),
        'unique_file_identifier' => array('ownerid' => 'info@boiseccc.org', 'data' => md5(time())),
    );
    echo "Updating tags in MP3 file... (see <a href=\"../$sermon_dir/bak/$filename-tags.txt\" target=\"_blank\">tag file</a>)\n";
//    var_dump($tag_data);
    file_put_contents('bak/' . $filename . "-tags.txt", json_encode($tag_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $tagwriter->tag_data = $tag_data;

    echo $tagwriter->filename."\n\n\n";
    if ($tagwriter->WriteTags()) {
        echo "Successfully wrote tags.\n";
        if (!empty($tagwriter->warnings)) {
            echo "There were some warnings:\n" . implode("\n", $tagwriter->warnings);
        }
    } else {
        die("Failed to write tags! Errors:\n" . implode("\n", $tagwriter->errors));
    }

    if (! $main_scripture) {
        if ($pptx_scriptures)
            $main_scripture = explode("\n", $pptx_scriptures)[0];
        else if ($docx_scriptures)
            $main_scripture = explode("\n", $docx_scriptures)[0];
    }
    $main_scripture = preg_replace('/【/', '', $main_scripture);
    $main_scripture = preg_replace('/】/', '', $main_scripture);
    $main_scripture = cleanUpScripture($main_scripture);
    echo "MAIN SCRIPTURE: $main_scripture\n";

    $series_id = makeSeries($series, $catid);
    $speaker_id = makeSpeaker($speaker, $catid);
    if (!$message_image || ! file_exists($message_image)) {
        if (!$image_verse)
            $image_verse = $main_scripture;
        $image_verse = trim(preg_replace('/[^A-Za-z0-9 _:,-]/', '', $image_verse)); # removes chinese characters
        $image_verse = preg_replace('/  +/', ' ', $image_verse); # Removes any double spaces
        $message_image = $filename . '.jpg';
    }

    $body_lines = array();
    if(trim($docx_scriptures))
        $body_lines[] = '<p>經文 Scripture:<ul><li>'.preg_replace('/ *\n */', '</li><li>', $docx_scriptures).'</li></ul></p>';
    $add_file = '';
    $add_file_desc = '';
    if (file_exists($message_pptx)) {
        $body_lines[] = '{google_docs}/' . $sermon_dir . '/' . basename($message_pptx) . $pptx_settings . '{/google_docs}';
        $add_file = '/' . $sermon_dir . '/' . basename($message_pptx);
        $add_file_desc = 'PowerPoint Slides';
    }
    if (file_exists($message_docx)) {
        if( ! $title_chinese) {
//$docObj = new DocxConversion("test.docx");
//$docObj = new DocxConversion("test.xlsx");
//$docObj = new DocxConversion("test.pptx");
            $title_chinese = getChineseTitleFromDocx($message_docx);
        }

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
    echo "BODY: $body\n";
    $audio_file = '/' . $sermon_dir . '/' . basename($message_mp3);
    $audio_file_size = filesize($message_mp3);

    $info = $getID3->analyze($message_mp3);
    $sermon_time = format_duration($info['playtime_string']);
    $title = $title_english . ($title_chinese?' ' . $title_chinese:'');
    echo("FINAL TITLE: $title_english |||| $title_chinese ===> $title\n");
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
    echo "(see <a href=\"../$sermon_dir/bak/$filename-row.txt\" target=\"_blank\"'>row data</a>)\n";
    deleteScriptures($sermon_id);
    if($main_scripture)
        makeScriptureRef($sermon_id, $main_scripture);
    $row['id'] = $sermon_id;
//    var_dump($row);
    file_put_contents('bak/' . $filename . "-row.txt", json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "DONE: series: $series_id, speaker: $speaker_id, sermon: $sermon_id\n\n";

    $conn->close();

    $sermon_dir = null;
    $filename = null;
    $ret = chdir('../../upload-sermon');
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
//	echo $sql . "\n\n";
	$result = $conn->query($sql);
	$row = mysqli_fetch_assoc($result);
	if ($row) {
		return $row['id'];
	} else {
		$row = array(
			'title' => $title,
			'alias' => strtolower(str_replace(' ', '-', trim(preg_replace('/[^A-Za-z0-9_-]/', '', $title)))),
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
//	echo $sql . "\n\n";
	$result = $conn->query($sql);
	$row = mysqli_fetch_assoc($result);
	if ($row) {
		return $row['id'];
	} else {
		$row = array(
			'title' => $title,
			'alias' => strtolower(str_replace(' ', '-', trim(preg_replace('/[^A-Za-z0-9_-]/', '', $title)))),
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
//	echo $sql . "\n\n";
	$conn->query($sql);
//	echo $conn->insert_id . "\n\n";
	return $conn->insert_id;
}

function updateTable($table, $row, $id) {
    global $conn;
    $sets = array();
    foreach ($row as $field => $value) {
        $sets[] = "$field = \"".mysqli_real_escape_string($conn, $value)."\"";
    }
    $sql = 'UPDATE `' . $table . '` SET '.implode(',', $sets).' WHERE id = '.$id;
//    echo $sql . "\n\n";
    $conn->query($sql);
    return $id;
}

function deleteScriptures($id) {
    global $conn, $prefix;
    $sql = 'DELETE FROM `' . $prefix . 'sermon_scriptures` WHERE sermon_id = '.$id;
//    echo $sql . "\n\n";
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
	    $script = preg_replace('/[^A-Za-z0-9 :;,_-]/', '', $script);
	    $script = trim($script);
	    if (strpos($script, ':') === false)
    	    $script = preg_replace('/(.+)(\d+) +(\d+)/', '\1\2:\3', $script);
        if (strpos($script, ';'))
            $script = preg_replace('/(.+?) ([\dabc:,-]+); *(\d+)/', '$1 $2; $1 $3', $script);
        if (strpos($script, ';')) {
            $scripts = explode(';', $script);
        } else {
            $scripts = array($script);
        }
        foreach($scripts as $s) {
            $s = cleanUpScripture($s);
            $ref = getScriptureRef($s);
            if ($ref && $ref['book']) {
                $ref['sermon_id'] = $sermon_id;
                if (!isset($refs[$s])) {
                    $refs[$s] = $ref;
                    $order[] = $s;
                }
            } else {
                $bad_refs[] = $s;
                $s = trim(explode(',', $s)[0]);
                $s = trim(explode(';', $s)[0]);
                $ref = getScriptureRef($s);
                if ($ref && $ref['book']) {
                    $ref['sermon_id'] = $sermon_id;
                    if (!isset($refs[$s])) {
                        $refs[$s] = $ref;
                        $order[] = $s;
                    }
                }
            }
        }
    }
    print("ORDER:");
    var_dump($order);
    print("REFS:");
    var_dump($refs);
    print("BAD:");
    var_dump($bad_refs);
    foreach($order as $script) {
        insertIntoTable($prefix . 'sermon_scriptures', $refs[$script]);
    }
    echo "Adding Scripture References... (see <a href=\"../$sermon_dir/bak/$filename-refs.txt\" target=\"_blank\">scripture refs</a>)\n";
    file_put_contents('bak/ ' . $filename . "-refs.txt", json_encode($refs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    if (count($bad_refs)) {
        echo "Has bad Scripture References! (see <a href=\"../$sermon_dir/bak/$filename-bad_refs.txt\" target=\"_blank\">bad refs</a>)\n";
        file_put_contents('bak/' . $filename . "-bad_refs.txt", json_encode($bad_refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
	preg_match('/^([^:-]+) (\d+):(\d[\dabc]*)$/', $str, $matches);
	if ($matches) {
		$ref['book'] = getBookNumber($matches[1]);
		$ref['cap1'] = $matches[2];
		$ref['vers1'] = preg_replace('/[^\d]/', '', $matches[3]);
		return $ref;
	}

	# Matthew 5:1-6
	preg_match('/^([^:-]+) (\d+):(\d[\dabc]*)[-, ]+(\d[\dab]*)$/', $str, $matches);
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
	print_r($scriptureRef);
	if (!$scriptureRef || !$scriptureRef['book'] || !$books[$scriptureRef['book']] || !$scriptureRef['cap1'])
		return null;
	$scripturePath = str_replace(' ', '_', strtolower($books[$scriptureRef['book']][0])) . '/' . $scriptureRef['cap1'] . '-' . ($scriptureRef['vers1'] ? $scriptureRef['vers1'] : 1);
	// LINUX:
    $command = "wget -O $basename $(curl https://biblepic.com/$scripturePath.htm | lynx --dump -listonly -stdin | sed -r 's/^\s*[0-9]+\. file:\/\/localhost/https:\/\/biblepic.com/' | grep jpg)";
    // MAC:
//    $command = "curl -o $basename $(curl https://biblepic.com/$scripturePath.htm | /usr/local/bin/lynx --dump -listonly -stdin | /usr/local/bin/gsed -r 's/^\s*[0-9]+\. file:\/\//https:\/\/biblepic.com/' | grep jpg)";
	echo $command . "\n";
	exec($command . " 2>&1", $output, $ret);
	var_dump($output);
    var_dump($ret);
	if(!file_exists($basename)) {
	    die('NO '.$basename);
    }
	return "$basename.jpg";
}

function redo_all_sermons() {
    global $conn, $server_name, $username, $password, $db_name, $prefix;

    $conn = new mysqli($server_name, $username, $password, $db_name);
    $conn->set_charset('utf8');
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $sql = "SELECT * FROM " . $prefix . "sermon_sermons WHERE sermon_date LIKE '2017-05%' ORDER BY sermon_date ASC";
    $result = $conn->query($sql);
    while($row = mysqli_fetch_assoc($result)){
        print_r($row);
        $date = date('Y-m-d', strtotime($row['sermon_date']));
        echo $date."\n\n";
        makeSermon($date);
        print("----------------------------------\n");
    }
}
