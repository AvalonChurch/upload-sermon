<?php
require_once('sermons.php');

$upload_dir = dirname(__FILE__) . "/uploaded";

$message_mp3 = null;
$message_ppt = null;
$message_docx = null;
$message_image = null;
$title_english = '';
$title_chinese = '';
$date = (new DateTime)->format('Y-m-d');
$catid = 19;
$series = "Matthew";
$speaker = "Barnabas Feng";
$scripture = '';
$image_verse = '';

date_default_timezone_set('America/Denver');

if(isset($_POST['submit'])) {
    $title_english = $_POST['title_english'];
    $title_chinese = $_POST['title_chinese'];
    $date = date('Y-m-d', strtotime($_POST['date']));
    $catid = intval($_POST['catid']);
    $series = $_POST['series'];
    $scripture = $_POST['scripture'];
    $scriptures = $_POST['docx_scriptures'];
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
    $message_ppt = null;
    if (isset($_FILES['message_ppt']) && $_FILES['message_ppt']['tmp_name']) {
        $tmpFilePath = $_FILES['message_ppt']['tmp_name'];
        if ($tmpFilePath != "") {
            $filePath = $myDir . "/" . $_FILES['message_ppt']['name'];
            if (move_uploaded_file($tmpFilePath, $filePath)) {
                $message_ppt = $filePath;
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
    <style>
        div {
            padding-bottom: 10px;
        }

        label {
            display: block;
        }

        .required:after {
            content: '*';
            color: red;
        }
    </style>
    <script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
    <script src="https://momentjs.com/downloads/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/docxtemplater/3.9.1/docxtemplater.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.6.1/jszip.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.0.2/jszip-utils.js"></script>

    <script type="text/javascript">
        var loadFile=function(url,callback){
            JSZipUtils.getBinaryContent(url,callback);
        };

        function getScriptureRefs(file) {
            var objectUrl = URL.createObjectURL(file);
            var verses = [];
            loadFile(objectUrl, function(err,content){
                var zip = new JSZip(content);
                var doc=new window.docxtemplater().loadZip(zip);
                var text = doc.getFullText();
                var title = text.split(/201/)[0];
                if(!$('#title-chinese').length)
                    $('#title-chinese').val(title);
                console.log(title);
                var re = /【([^】]+)】/g;
                var m;
                do {
                    m = re.exec(text);
                    if (m) {
                        var verse = m[1].trim();
                        verse = verse.replace(/  +/g, " ");
                        if (! verses.includes(verse)) {
                            verses.push(verse);
                        }
                    }
                } while (m);
            });
            return verses;
        }

        function getSingleVerse(verses) {
            var singleVerse;
            if (verses.length > 0) {
                singleVerse = verses[0].replace(/[^\w :-]+/g, " ");
                singleVerse = singleVerse.split(/[,;-]/)[0];
                singleVerse = singleVerse.trim();
            }
            return singleVerse;
        }

        $(document).ready(function(){
            $('input#message-mp3').change(function(){
                var filename = $(this).val().split('\\').pop();
                var parts = filename.split(/[_.]/);
                var date = null;
                if(parts.length > 1) {
                    date = parts[0];
                }
                if (date.length === 6) {
                    date = "20" + date.substring(0, 2) + "-" + date.substr(2, 2) + "-" + date.substr(4, 2);

                }
                var isDate = Date.parse(date);
                if (isDate) {
                    var dateStr = moment(date).format('YYYY-MM-DD');
                    $('#date').val(dateStr);
                }
            });

            $('input#message-ppt').change(function(){
                var filename = $(this).val().split('\\').pop();
                var parts = filename.split(/[_.]/);
                var date = parts[0];
                var title = null;
                if (parts.length > 2)
                    title = parts[1];
                if(date.length !== 10 && parts.length > 3) {
                    date = parts[0] + " " + parts[1] + ", " + parts[2];
                    if (parts.length > 4)
                        title = parts[3];
                }
                var isDate = Date.parse(date);
                if (isDate) {
                    var dateStr = moment(date).format('YYYY-MM-DD');
                    var dateObj = new Date(dateStr);
                    $('#date').val(dateStr);
                    $('#title-english').val(title);
                }
            });

            $('input#message-notes').change(function(){
                var filename = $(this).val().split('\\').pop();
                var parts = filename.split(/[_.]/);
                var date = parts[0];
                var title = null;
                if (parts.length > 2)
                    title = parts[1];
                if(date.length !== 10 && parts.length > 3) {
                    date = parts[0] + " " + parts[1] + ", " + parts[2];
                    if (parts.length > 4)
                       title = parts[3];
                }
                var isDate = Date.parse(date);
                if (isDate) {
                    var dateStr = moment(date).format('YYYY-MM-DD');
                    $('#date').val(dateStr);
                    $('#title-chinese').val(title);
                }
            });

            function putValueBackFromPlaceholder(id) {
                var $this = $('#'+id);
                if ($this.val() === '') {
                    $this.val($this.attr('placeholder'));
                    $this.attr('placeholder','');
                }
            }
            function clickDatalist(e) {
                var $this = $(this);
                var inpLeft = $this.offset().left;
                var inpWidth = $this.width();
                var clickedLeft = e.clientX;
                var clickedInInpLeft = clickedLeft - inpLeft;
                var arrowBtnWidth = 12;
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

            $('#series').on('click', clickDatalist)
                .on('mouseleave', putValueBackFromPlaceholder, 'series');
            $('#speaker').on('click', clickDatalist)
                .on('mouseleave', putValueBackFromPlaceholder, 'speaker');

            var audio = new Audio();
            function pad(num, size) {
                var s = num+"";
                while (s.length < size) s = "0" + s;
                return s;
            }
            audio.addEventListener("timeupdate", function() {
                console.log('ct: '+audio.currentTime);
            });
            audio.addEventListener("canplaythrough", function() {
                console.log('d: '+audio.duration);
                $("#duration").html(pad(Math.floor(audio.duration/3600),2) + ':' + pad(Math.floor((audio.duration%3600)/60),2) + ':' + pad(Math.round(audio.duration%60),2));
            });
            $('input#message-mp3').change(function(e) {
                var file = e.currentTarget.files[0];
                var objectUrl = URL.createObjectURL(file);
                audio.src = objectUrl;
            });

            $('input#message-docx').change(function(e) {
                var file = e.currentTarget.files[0];
                var verses = getScriptureRefs(file);
                if (verses.length > 0) {
                    var mainVerse = verses[0];
                    $('#scripture').val(mainVerse);
                    var singleVerse = getSingleVerse(verses);
                    $('#image-verse').val(singleVerse);
                    $("#scripture-docx").html("All verses in DOCX: <ul><li>" + verses.join("</li><li>") + "</li></uL>");
                }
            });

            $('input#message-pptx').change(function(e) {
                var file = e.currentTarget.files[0];
                var verses = getScriptureRefs(file);
                if (verses.length > 0) {
                    var mainVerse = verses[0];
                    if(! $('#scripture').val().length)
                        $('#scripture').val(mainVerse);
                        var singleVerse = getSingleVerse(verses);
                    if(! $('#image-verse').val().elgnth)
                        $('#image-verse').val(singleVerse);
                    $("#scripture-pptx").html("All verses in PPTX: <ul><li>" + verses.join("</li><li>") + "</li></uL>");
                }
            });
        });

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
        echo "<li>PPTX: ".($message_ppt?$message_ppt:"none")."</li>";
        echo "<li>DOCX: ".($message_docx?$message_docx:"none")."</li>";
        echo "</ul>";
    }
    ?>

    <pre>
<?php
    makeSermon($message_mp3, $message_ppt, $message_docx, $message_image, $title_english, $title_chinese, $date, $catid, $series, $speaker, $scripture, $scriptures, $image_verse);
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
    </div>

    <div>
        <label for="message-ppt">Message PPTX</label>
        <input id="message-ppt" name="message_ppt" type="file" accept=".pptx"/>
    </div>

    <div>
        <label for="message-docx">Message DOCX</label>
        <input id="message-docx" name="message_docx" type="file" accept=".doc,.docx"/>
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
            <option value="19"<?php echo ($catid=="19"?" selected":"")?>>Sunday Sermon</option>
            <option value="21"<?php echo ($catid=="21"?" selected":"")?>>Friday Lesson</option>
            <option value="1"<?php echo ($catid=="1"?" selected":"")?>>Special Message</option>
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

    <div style="border: black solid;clear:both;">
        <label for="image-verse">Image Verse</label>
        <input type="text" id="image-verse" name="image_verse" value="<?php echo $image_verse?>" size="20">
        <br/>
        If this is empty, will attempt to get the first verse of the passage in the "Scripture" field.
        This is to be a verse that captures the message. If Message Image file field (below) is used, then this is ignored.
        <p>OR</p>
        <label for="message-image">Message Image (can make your own from <a href="https://biblepic.com/genesis/1-1.htm" target="_blank">here</a> or find using <a href="https://www.google.com/search?q=Genesis+1:1&newwindow=1&safe=strict&source=lnms&tbm=isch&sa=X&ved=0ahUKEwjx2-a75bHgAhVXJzQIHcd6Bl8Q_AUIDigB&biw=1164&bih=601" target="_blank">Google</a>)</label>
        <input id="message-image" name="message_image" type="file" accept=".gif,.jpg,.jpeg,.png"/>
    </div>


    <p><input type="submit" name="submit" value="Submit"></p>
</form>
</body>
</html>
