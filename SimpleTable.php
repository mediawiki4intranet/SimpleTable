<?php

/*
 * Tabbed Data extension.
 *
 * This extension implements a simple method to display tabular data, with a
 * far simpler markup than the standard Wiki table markup.  The goal is to
 * allow tabular data to be very easily pasted in from an external source,
 * such as a spreadsheet.  The disadvantage is that it doesn't allow for any
 * fancy formatting; for example, there is no way (as yet) to set row and cell
 * parameters, including row and cell spanning.  However, it makes a very
 * simple way to include tabular data in a Wiki.
 *
 * All you need to do is prepare your data in rows, with fields separated
 * by tab characters.  Excel's "Save as" -> "Text (Tab delimited)" function
 * saves data in this format.  Place the data inside <tab>...</tab> tags,
 * and set any table parameters inside the opening <tab> tag; eg.:
 *
 *   <tab class=wikitable>
 *   field1\tfield2\t...
 *   field1\tfield2\t...
 *   </tab>
 *
 * Additional parameters allowed in the tag are:
 *   sep        Specify a different separator; see the $separators array.
 *   head       Specify a heading; "head=top" makes the first row a heading,
 *              "head=left" makes the first column a heading, "head=topleft"
 *              does both.
 *
 * Thanks for contributions to:
 *  Smcnaught
 *  Frederik Dohr
 */

$wgExtensionFunctions[] = 'wfSimpleTable';
$wgExtensionCredits['parserhook'][] = array(
  'name'=>'SimpleTable',
  'author'=>'Johan the Ghost',
  'url'=>'http://www.mediawiki.org/wiki/Extension:SimpleTable',
  'description'=>'Convert tab-separated or similar data into a Wiki table',
);


/*
 * Setup SimpleTable extension.
 * Sets a parser hook for <tab></tab>.
 */
function wfSimpleTable() {
    new SimpleTable();
}


class SimpleTable {

    /*
     * The permitted separators.  An array of separator style name
     * and preg pattern to match it.
     */
    private $separators = array(
        'space' => '/ /',
        'spaces' => '/\s+/',
        'tab' => '/\t/',
        'comma' => '/,/',
        'bar' => '/\|/',
    );


    /*
     * Construct the extension and install it as a parser hook.
     */
    public function __construct() {
        global $wgParser;
        $wgParser->setHook('tab', array(&$this, 'hookTab'));
    }


    /*
     * The hook function. Handles <tab></tab>.
     * Receives the table content and <tab> parameters.
     */
    public function hookTab($tableText, $argv, $parser) {
        // The default field separator.
        $sep = 'tab';

        // Default value for using table headings.
        $head = null;

        // Build the table parameters string from the tag parameters.
        // The 'sep' and 'head' parameters are special, and are handled
        // here, not passed to the table.
        $params = '';
        foreach (array_keys($argv) as $key) {
            if ($key == 'sep')
                $sep = $argv[$key];
            else if ($key == 'head')
                $head = $argv[$key];
            else
                $params .= ' ' . $key . '="' . $argv[$key] . '"';
        }

        if (!array_key_exists($sep, $this->separators))
            return "Invalid separator: $sep";

        // Parse and convert the table body:

        // Parse preserving line-feeds
        $uniq = $parser->insertStripItem("\x01");
        $tableText = str_replace(array("\r\n", "\n\r", "\n", "\r"), array($uniq, $uniq, $uniq, ''), $tableText);
        $html = $parser->parse($tableText, $parser->mTitle, $parser->mOptions, false, false)->getText();
        $html = str_replace(array("\r\n", "\n\r", "\n", "\r"), array(' ', ' ', ' ', ' '), $html);
        $html = explode("\x01", trim($html, "\x01 \t\n\r"));

        // Build HTML <table> content
        $table = '';
        $headtop = strpos($head, 'top') !== false;
        $headleft = strpos($head, 'left') !== false;
        foreach ($html as $i => $line)
        {
            $line = preg_replace('/<!--.*?-->/', '', $line);
            if (trim($line))
            {
                $line = preg_split($this->separators[$sep], $line);
                if ($headtop && !$i)
                    $line = '<th>'.implode('</th><th>', $line).'</th>';
                elseif ($headleft)
                    $line = ('<th>'.array_shift($line).'</th>') . ('<td>'.implode('</td><td>', $line).'</td>');
                else
                    $line = '<td>'.implode('</td><td>', $line).'</td>';
                $table .= '<tr>' . $line . "</tr>\n";
            }
        }
        $html = "<table$params>$table</table>";

        return $html;
    }

}
