<?php
require_once('sermons.php');

$upload_dir = dirname(__FILE__) . "/uploaded";

$message_mp3 = null;
$message_pptx = null;
$message_docx = null;
$message_image = null;
$title_english = '';
$title_chinese = '';
$date = (new DateTime)->format('Y-m-d');
$catid;
$series;
$speaker;
$scripture = '';
$image_verse = '';

date_default_timezone_set('America/Denver');

if(isset($_POST['submit'])) {
    $title_english = $_POST['title_english'];
    $title_chinese = $_POST['title_chinese'];
    $date = date('Y-m-d', strtotime($_POST['date']));
    $catid = intval($_POST['catid']);
    $series = $_POST['series'];
    $speaker = $_POST['speaker'];
    $scripture = $_POST['scripture'];
    $image_verse = $_POST['image_verse'];

    $timestamp = date('Y-m-d H-i-s').' - '.time();
    $myDir = $upload_dir . '/' . $timestamp;

    if (!file_exists($myDir)) {
        mkdir($myDir, 0777, true);
    }

    $message_mp3 = null;
    if (isset($_FILES['message_mp3']) && $_FILES['message_mp3']['tmp_name']) {
        $tmpFilePath = $_FILES['message_mp3']['tmp_name'];
        if ($tmpFilePath != "") {
            $filePath = $myDir . "/" . $_FILES['message_mp3']['name'];
            if (move_uploaded_file($tmpFilePath, $filePath)) {
                $message_mp3 = $filePath;
            }
        }
    }
    $message_pptx = null;
    if (isset($_FILES['message_pptx']) && $_FILES['message_pptx']['tmp_name']) {
        $tmpFilePath = $_FILES['message_pptx']['tmp_name'];
        if ($tmpFilePath != "") {
            $filePath = $myDir . "/" . $_FILES['message_pptx']['name'];
            if (move_uploaded_file($tmpFilePath, $filePath)) {
                $message_pptx = $filePath;
            }
        }
    }
    $message_docx = null;
    if (isset($_FILES['message_docx']) && $_FILES['message_docx']['tmp_name']) {
        $tmpFilePath = $_FILES['message_docx']['tmp_name'];
        if ($tmpFilePath != "") {
            $filePath = $myDir . "/" . $_FILES['message_docx']['name'];
            if (move_uploaded_file($tmpFilePath, $filePath)) {
                $message_docx = $filePath;
            }
        }
    }
    $message_image = null;
    if (isset($_FILES['message_image']) && $_FILES['message_image']['tmp_name']) {
        $tmpFilePath = $_FILES['message_image']['tmp_name'];
        if ($tmpFilePath != "") {
            $filePath = $myDir . "/" . $_FILES['message_image']['name'];
            if (move_uploaded_file($tmpFilePath, $filePath)) {
                $message_image = $filePath;
            }
        }
    }

    file_put_contents($myDir . "/_POST.txt", json_encode($_POST, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    file_put_contents($myDir . "/_FILES.txt", json_encode($_FILES, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
}
?>
<html>
<head>
    <title>BCCC Sermon Uploader/Updater</title>
    <style>
        body {
            margin: 5px !important;
        }
        textarea {
            display: block !important;
        }
        div {
            padding-bottom: 5px !important;
        }

        label {
            display: block !important;
        }

        .required:after {
            content: '*';
            color: red;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://momentjs.com/downloads/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/south-street/jquery-ui.css">
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/docxtemplater/3.9.1/docxtemplater.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.6.1/jszip.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.0.2/jszip-utils.js"></script>

    <script type="text/javascript">
        let loadFile=function(url, callback){
            JSZipUtils.getBinaryContent(url, callback);
        };

        function getScriptureRefs(file, callback) {
            let objectUrl = URL.createObjectURL(file);
            var verses = [];
            loadFile(objectUrl, function(err, content){
                let zip = new JSZip(content);
                let doc= new window.docxtemplater().loadZip(zip);
                let text = doc.getFullText();
                let title = text.split(/201/)[0]; // splits on the date which starts with 201
                if(title.length && !$('#title-chinese').length && file.endsWith('docx') && title.length < 200) {
                    $('#title-chinese').val(title);
                    console.log(title);
                }
                console.log(text);
                let re = /【([^】]+)】/g;
                let m;
                do {
                    m = re.exec(text);
                    if (m) {
                        let verse = m[1].trim();
                        verse = verse.replace(/  +/g, " ");
                        if (! verses.includes(verse)) {
                            console.log(verse);
                            verses.push(verse);
                        }
                    }
                } while (m);
                callback(verses);
            });
        }

        function getSingleVerse(verses) {
            let singleVerse;
            if (verses.length > 0) {
                singleVerse = verses[0].replace(/[^\w :-]+/g, " ");
                singleVerse = singleVerse.split(/[,;-]/)[0];
                singleVerse = singleVerse.trim();
            }
            return singleVerse;
        }

        function getDateFromFile(path) {
            let filename = path.split('\\').pop();
            let parts = filename.split(/[_.]/);
            let date = null;
            if(parts.length > 1) {
                date = parts[0];
            }
            if (date.length === 6) {
                date = "20" + date.substring(0, 2) + "-" + date.substr(2, 2) + "-" + date.substr(4, 2);

            }
            let isDate = Date.parse(date);
            if (isDate) {
                return moment(date).format('YYYY-MM-DD');
            }
        }

        function getTitleFromFile(path) {
            let filename = path.split('\\').pop();
            let parts = filename.split(/[_.]/);
            if (parts.length > 2)
                return parts[1];
        }

        function pad(num, size) {
            let s = num+"";
            while (s.length < size) s = "0" + s;
            return s;
        }

        function putValueBackFromPlaceholder(id) {
            let $this = $('#'+id);
            if ($this.val() === '') {
                $this.val($this.attr('placeholder'));
                $this.attr('placeholder','');
            }
        }
        function clickDatalist(e) {
            let $this = $(this);
            let inpLeft = $this.offset().left;
            let inpWidth = $this.width();
            let clickedLeft = e.clientX;
            let clickedInInpLeft = clickedLeft - inpLeft;
            let arrowBtnWidth = 12;
            if ((inpWidth - clickedInInpLeft) < arrowBtnWidth ) {
                if ($this.val() != "") {
                    $this.attr('placeholder', $this.val());
                    $this.val('');
                }
            }
            else {
                putValueBackFromPlaceholder($this.prop('id'));
            }
        }

        $(document).ready(function(){
            let NO_DATE = "No date found in filename. Please specify a date in the date field below.";
            let audio = new Audio();

            audio.addEventListener("timeupdate", function() {
                console.log('ct: '+audio.currentTime);
            });
            audio.addEventListener("canplaythrough", function() {
                console.log('d: '+audio.duration);
                $("#duration").html(pad(Math.floor(audio.duration/3600),2) + ':' + pad(Math.floor((audio.duration%3600)/60),2) + ':' + pad(Math.round(audio.duration%60),2));
            });

            $('#message-mp3').change(function(e){
                let date = getDateFromFile($(this).val());
                if (date) {
                    $('#date').val(date);
                    $('#mp3-date').html(date);
                    if(($('#pptx-date').html().length && $('#pptx-date').html() != date) ||
                        ($('#docx-date').html().length && $('#docx-date').html() != date)) {
                        alert("FILE DATES DO NOT MATCH!!!!");
                    }
                } else {
                    $('#mp3-date').html(NO_DATE);
                }

                // Get audio length
                let file = e.currentTarget.files[0];
                let objectUrl = URL.createObjectURL(file);
                audio.src = objectUrl;
            });

            $('#message-pptx').change(function(e){
                let date = getDateFromFile($(this).val());
                let title = getTitleFromFile($(this).val());
                if (date) {
                    $('#date').val(date);
                    $('#pptx-date').html(date);
                    if(($('#mp3-date').html().length && $('#mp3-date').html() != date) ||
                        ($('#docx-date').html().length && $('#docx-date').html() != date)) {
                        alert("FILE DATES DO NOT MATCH!!!!");
                    }
                } else {
                    $('#pptx-date').html(NO_DATE);
                }
                if(title) {
                    $('#title-english').val(title);
                } else {
                    alert("English Sermon Name Not Found in File Name! Right file? Please enter it in the English Title field.")
                }

                // Extract scripture verses
                let file = e.currentTarget.files[0];
                getScriptureRefs(file, function(verses) {
                    console.log(verses);
                    if (verses.length > 0) {
                        let mainVerse = verses[0];
                        if (!$('#scripture').val().length)
                            $('#scripture').val(mainVerse);
                        let singleVerse = getSingleVerse(verses);
                        if (!$('#image-verse').val().length)
                            $('#image-verse').val(singleVerse);
                        $("#scripture-pptx").html("First verse in PPTX: <ul><li>" + verses.join("</li><li>") + "</li></uL>");
                    }
                });
            });

            $('#message-docx').change(function(e){
                let date = getDateFromFile($(this).val());
                let title = getTitleFromFile($(this).val());
                if (date) {
                    $('#date').val(date);
                    $('#docx-date').html(date);
                    if(($('#mp3-date').html().length && $('#mp3-date').html() != date) ||
                        ($('#pptx-date').html().length && $('#pptx-date').html() != date)) {
                        alert("FILE DATES DO NOT MATCH!!!!");
                    }
                } else {
                    $('#docx-date').html(NO_DATE);
                }
                if(title) {
                    $('#title-chinese').val(title);
                } else {
                    alert("Chinese Sermon Name Not Found in File Name! Right file? Please enter it in the Chinese Title field.")
                }

                // Extract scripture verses
                let file = e.currentTarget.files[0];
                getScriptureRefs(file, function(verses) {
                    console.log(verses);
                    if (verses.length > 0) {
                        let mainVerse = verses[0];
                        $('#scripture').val(mainVerse);
                        let singleVerse = getSingleVerse(verses);
                        $('#image-verse').val(singleVerse);
                        $("#scripture-docx").html("All verses in DOCX: <ul><li>" + verses.join("</li><li>") + "</li></uL>");
                    }
                });
            });

            $('#series').on('click', clickDatalist)
                .on('mouseleave', putValueBackFromPlaceholder, 'series');
            $('#speaker').on('click', clickDatalist)
                .on('mouseleave', putValueBackFromPlaceholder, 'speaker');
        });
    </script>
</head>
<body>

<?php if ($_POST['submit']): ?>
    <?php
    if($message_mp3) {
        //show success message
        echo "<b>Uploaded:</b>";
        echo "<ul>";
        echo "<li>MP3: ".($message_mp3?$message_mp3:"none")."</li>";
        echo "<li>PPTX: ".($message_pptx?$message_pptx:"none")."</li>";
        echo "<li>DOCX: ".($message_docx?$message_docx:"none")."</li>";
        echo "</ul>";
    }
    ?>

    <pre>
<?php
    makeSermon($date, $message_mp3, $message_pptx, $message_docx, $message_image, $title_english, $title_chinese, $catid, $series, $speaker, $scripture, $image_verse);
?>
    </pre>
<?php endif;?>

<h1>Sermon Upload/Update</h1>
<ul><li>This form allows you to both upload new sermons or update existing ones.</li>
    <li>This is all by date, so a single date can only have one sermon, and that is the unique
identifier to know if you are adding a new one or updating an existing one.</li>
    <li>If you are updating an existing one, and the MP3, PPTTX or DOCX already on the website are good, you do not need to add them again, as it will copy the old ones.</li>
    <li>For the scripture image, if you want to keep the old one, you need to make sure the Image Verse field is empty.</li>
    <li>For a new sermon, if you don't have an MP3, please still create an sermon with whatever files are available. You do NOT have to add an MP3. A dummy MP3 will be created saying there is no audio for the sermon.</li>
</ul>
<form action="" enctype="multipart/form-data" method="post">

    <div>
        <label for="message-mp3">Message MP3</label>
        <input id="message-mp3" name="message_mp3" type="file" accept=".mp3"/>
        <div>Duration: <span id="duration">--:--:--</span></div>
        <div id="mp3-date" style="font-weight:bold;"></div>
    </div>

    <div>
        <label for="message-pptx">Message PPTX</label>
        <input id="message-pptx" name="message_pptx" type="file" accept=".pptx"/>
        <div id="pptx-date" style="font-weight:bold;"></div>
    </div>

    <div>
        <label for="message-docx">Message DOCX</label>
        <input id="message-docx" name="message_docx" type="file" accept=".doc,.docx"/>
        <div id="docx-date" style="font-weight:bold;"></div>
    </div>

    <p/>
    <hr/>
    <p/>

    <div>
        <label for="title-english" class="required">Message Title (English)</label>
        <input type="text" id="title-english" name="title_english" size="50" value="<?php echo $title_english?>" required/>
        <br/>
        <label for="title-chinese" class="required">Message Title (Chinese)</label>
        <input type="text" id="title-chinese" name="title_chinese" size="50" value="<?php echo $title_chinese?>" required/>
    </div>

    <div>
        <label for="date" class="required">Message date</label>
        <input type="date" id="date" name="date" value="<?php echo $date?>" required/>
        <br/>
        Please make sure this is the date of the sermon/message!
    </div>

    <p/>
    <hr/>
    <p/>

    <div>
        <label for="catid" class="required">Category</label>
        <select id="catid" name="catid" required>
            <option value="19"<?php echo ($catid==19?" selected":"")?>>Sunday Sermon</option>
            <option value="21"<?php echo ($catid==21?" selected":"")?>>Friday Lesson</option>
            <option value="42"<?php echo ($catid==42?" selected":"")?>>Special Message</option>
        </select>
    </div>

    <div>
        <label for="series" class="required">Series</label>
        <input id="series" name="series" type="text" list="series_list" value="<?php echo $series?>"  required/>
        <datalist id="series_list">
            <option value="Matthew">Matthew</option>
            <option value="Exodus">Exodus</option>
            <option value="Genesis">Genesis</option>
            <option value="Holiday Message">Holiday Message</option>
            <option value="BCCC Sermon">BCCC Sermon</option>
        </datalist>
    </div>

    <div>
        <label for="speaker" class="required">Speaker</label>
        <input id="speaker" name="speaker" type="text" list="speaker_list" value="<?php echo $speaker?>" required/>
        <datalist id="speaker_list">
            <option>Barnabas Feng</option>
            <option>Abraham Chen</option>
            <option>Fwu-Shan Shieh</option>
        </datalist>
    </div>

    <p/>
    <hr/>
    <p/>

    <div>
        <label for="scripture">Main Sermon Scripture</label>
        <textarea id="scripture" name="scripture" rows="5" cols="100"><?php echo $scripture?></textarea>
        <br/>
        Put each scripture reference on its own line. Please use full book name with chapter and verse(s)
        <div id="scripture-docx" style="padding-top:10px;width:50%;float:left"></div>
        <div id="scripture-pptx" style="padding-top:10px;width:50%;float:left"></div>
    </div>

    <div style="border: black solid;clear:both;padding: 5px;margin:5px;">
        <label for="image-verse">Image Verse</label>
        <input type="text" id="image-verse" name="image_verse" value="<?php echo $image_verse?>" size="20">
        <br/>
        If this is empty, will attempt to get the first verse of the passage in the "Scripture" field.
        This is to be a verse that captures the message. If Message Image file field (below) is used, then this is ignored.
        <p>OR</p>
        <label for="message-image">Message Image (can make your own from <a href="https://biblepic.com/genesis/1-1.htm" target="_blank">here</a> or find using <a href="https://www.google.com/search?q=Genesis+1:1&newwindow=1&safe=strict&source=lnms&tbm=isch&sa=X&ved=0ahUKEwjx2-a75bHgAhVXJzQIHcd6Bl8Q_AUIDigB&biw=1164&bih=601" target="_blank">Google</a>)</label>
        <input id="message-image" name="message_image" type="file" accept=".gif,.jpg,.jpeg,.png"/>
    </div>

    <p><button class="btn btn-primary" type="submit" name="submit" value="Submit">Submit</button> <button class="btn btn-reset btn-secondary" type="reset" value="Reset">Reset</button></p>
</form>
</body>
</html>
