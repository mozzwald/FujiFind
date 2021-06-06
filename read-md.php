<?php
require_once('vendor/autoload.php');

$article_url = "";
$article_html = "";
$error_text = "";
$loc = "US";

if( isset( $_GET['loc'] ) ) {
    $loc = strtoupper($_GET["loc"]);
}

if( isset( $_GET['a'] ) ) {
    $article_url = $_GET["a"];
} else {
    echo "What do you think you're doing... >:(";
    exit();
}

if (substr( $article_url, 0, 4 ) != "http") {
    echo("That's not a web page :(");
    die();
}

$host = parse_url($article_url, PHP_URL_HOST);

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;

$configuration = new Configuration();
$configuration
    ->setArticleByLine(false)
    ->setFixRelativeURLs(true)
    ->setOriginalURL('http://' . $host);

$readability = new Readability($configuration);

use League\HTMLToMarkdown\HtmlConverter;

$converter_options = array(
						 'strip_placeholder_links' => true,
						 'strip_tags' => true
						 );
$converter = new HtmlConverter($converter_options);

if(!$article_html = file_get_contents($article_url)) {
    $error_text .=  "Failed to get the article :( <br>";
}

try {
    $readability->parse($article_html);
    $readable_article = strip_tags($readability->getContent(), '<a><ol><ul><li><br><p><small><font><b><strong><i><em><blockquote><h1><h2><h3><h4><h5><h6>');
    $readable_article = str_replace( 'strong>', 'b>', $readable_article ); //change <strong> to <b>
    $readable_article = str_replace( 'em>', 'i>', $readable_article ); //change <em> to <i>
    // Get rid of links for now until we can handle them on the Atari
	$readable_article = preg_replace('#<a.*?>(.*?)</a>#is', '\1', $readable_article);
    $readable_article = clean_str($readable_article);
    $readable_article = str_replace( 'href="http', 'href="read-md.php?a=http', $readable_article ); //route links through proxy
	$markdown = $converter->convert($readable_article);
	$markdown = preg_replace('#&amp;#', '&', $markdown);
	
} catch (ParseException $e) {
    $error_text .= 'Sorry! ' . $e->getMessage() . '<br>';
}

//replace chars that old machines probably can't handle
function clean_str($str) {
    $str = str_replace( "‘", "'", $str );    
    $str = str_replace( "’", "'", $str );  
    $str = str_replace( "“", '"', $str ); 
    $str = str_replace( "”", '"', $str );
    $str = str_replace( "–", '-', $str );

    return $str;
}

// Wrap text to 37 columns for Atari BASIC
$markdown = wordwrap($markdown, 37, "\n");

// Go line by line and parse the Markdown into ATASCII
// https://stackoverflow.com/a/14789147
$separator = "\n";
$line = strtok($markdown, $separator);
$atascii = "";

while ($line !== false) {
	//echo $line; // Before

	// Replace the headers with inverse ATASCII
	if ( preg_match("/# /", substr($line, 0, 6)) ) // line starts with #
	{
		$line = preg_replace('/(\#)\1+/', "", $line);
		$newline = "";
		for ( $pos=0; $pos < strlen($line); $pos ++ ) {
			$byte = substr($line, $pos);
			$newline .= chr(ord($byte)+128); // Inverse ATASCII the whole line
		}
		$line = $newline;
	}
	
    //echo $line . PHP_EOL; // After
	$atascii .= $line . chr(155); // Atari EOL
    $line = strtok( $separator ); // do it again
}

// Inverse ATASCII the Title
$title = $readability->getTitle();
$newline = "";
for ( $pos=0; $pos < strlen($title); $pos ++ ) {
	$byte = substr($title, $pos);
	$newline .= chr(ord($byte)+128); // Inverse ATASCII the whole line
}

echo $newline . chr(155) . $atascii;
//echo "### " . $readability->getTitle() . PHP_EOL . $atascii;
exit;

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 2.0//EN">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
 
 <html>
 <head>
     <title><?php echo $readability->getTitle();?></title>
 </head>
 <body>
    <p>
        <form action="read-md.php" method="get">
        <a href="./">Back to <b><font color="#008000">Frog</font><font color="000000">Find!</font></a></b> | Browsing URL: <input type="text" size="38" name="a" value="<?php echo $article_url ?>">
        <input type="submit" value="Go!">
        </form>
    </p>
    <hr>
    <h1><?php echo clean_str($readability->getTitle());?></h1>
    <p> <?php
        $img_num = 0;
        $imgline_html = "View page images:";
        foreach ($readability->getImages() as $image_url):
            //we can only do png and jpg
            if (strpos($image_url, ".jpg") || strpos($image_url, ".jpeg") || strpos($image_url, ".png") === true) {
                $img_num++;
                $imgline_html .= " <a href='image.php?loc=" . $loc . "&i=" . $image_url . "'>[$img_num]</a> ";
            }
        endforeach;
        if($img_num>0) {
            echo  $imgline_html ;
        }
    ?></small></p>
    <?php if($error_text) { echo "<p><font color='red'>" . $error_text . "</font></p>"; } ?>
    <p><font size="4"><?php echo $readable_article;?></font></p>
 </body>
 </html>