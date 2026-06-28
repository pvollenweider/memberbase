#!/usr/bin/perl -wl
################################################################################
# label generator
# This script generate labels for word. Format is A4, 24 labels per page
# (c) 2005 Philippe Vollenweider <pol@casa-alianza.ch>
################################################################################
# Usage: label.txt input.txt > output.html
#
# imput format is simple text with tablulation separator:
#    sexe society lastName firstName address npa
################################################################################
use strict;

print <<EOH;
<html xmlns:o="urn:schemas-microsoft-com:office:office"
xmlns:w="urn:schemas-microsoft-com:office:word"
xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv=Content-Type content="text/html; charset=ISO-8859-1">
<meta name=ProgId content=Word.Document>
<link rel=File-List href="">
<title>Etiquettes</title>
<!--[if gte mso 9]><xml>
 <w:WordDocument>
  <w:HyphenationZone>21</w:HyphenationZone>
 </w:WordDocument>
</xml><![endif]-->
<style>
<!--
 /* Style Definitions */
p
	{mso-style-parent:"";
	margin:0cm;
	margin-bottom:.0001pt;
	mso-pagination:widow-orphan;
	font-size:10.0pt;
	font-family:"Arial";
	mso-fareast-font-family:"Arial";
	margin-top:0cm;margin-right:20pt;margin-bottom:0cm;margin-left:20pt;margin-bottom:.0001pt;}
\@page Section1
	{size:595.3pt 841.9pt;
	margin:0cm 0cm 0cm 0cm;
	mso-header-margin:36.0pt;
	mso-footer-margin:36.0pt;
	mso-page-numbers:1;
	mso-paper-source:4;}
div.Section1
	{page:Section1;}
-->
</style>
</head>
<body lang=FR style='tab-interval:35.4pt'>

EOH
my $cell=0;
while (<>) {
    chomp;
    my ($sexe,$society,$lastName,$firstName,$address,$npa) = split /\t/,$_;
	if ($cell%24==0){
		print "<div class=Section1><table border=0 cellspacing=0 cellpadding=0 " .
		      "style='border-collapse:collapse;mso-padding-top-alt:0cm;mso-padding-bottom-alt:0cm'>";
	}
	if ($cell%3==0){
		print "<tr style='height:104.9pt'>";
 	}
    print"<td width=265 style='width:198.4pt;padding:0cm .75pt 0cm .75pt;height:104.9pt'>";
	print"<p>";
	if ($society) {
	    print $society . "<br/>";
	}
	if ($sexe ne '-') {
        print $sexe . "<br/>";
    }
	print "<p>$firstName $lastName</p>";
	if ($address) {
	    print "<p>$address</p>";
	}
	if ($npa) {
	    print "<p>$npa</p>";
	}
	print "<p><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></p>";
	print "<p><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></p>	</td>";
	$cell++;
	if ($cell%3==0){
		print "</tr>";
	}
	if ($cell%24==0){
		print "</table></div><span style='font-size:12.0pt;font-family:Arial;".
		      "mso-fareast-font-family:Arial;display:none;mso-hide:all;".
		      "mso-ansi-language:FR;mso-fareast-language:FR;".
		      "mso-bidi-language:AR-SA'><br clear=all ".
		      "style='page-break-before:always;mso-break-type:section-break'></span>";
	}
}

print <<EOP;
<div class=Section1>
<p><span style='display:none;mso-hide:all'><![if !supportEmptyParas]>&nbsp;<![endif]><o:p></o:p></span></p>
</div>

</body>
</html>
EOP
