<?php
require_once __DIR__ . '/includes/auth.inc';
requireLogin();
ob_start();
include "includes/declarations.inc";
include "classes/user_class.inc";
include "classes/compta_class.inc";

$userid = -1;
if (isset($_REQUEST['userid'])) {
    $userid = $_REQUEST['userid'];
}
$user = new User();
$user->lookupUser($userid);

$charset = "UTF-8";
$_safeLast  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $user->lastName);
$_safeFirst = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $user->firstName);
$filename   = "quit_{$_safeLast}_{$_safeFirst}_" . date("Ymd") . ".mhtml";
header("Content-Disposition: attachment; filename=\"$filename\"");
#header("Content-type: application/msword; charset=utf-8");
header("Content-type: message/rfc822; charset=utf-8");
header("Pragma: no-cache");
header("Expires: 0");

$comptaid =  $_REQUEST['comptaid'];
$compta = new Compta();
$compta->lookupCompta($comptaid);
$sum = $compta->sum;
if (! strstr($sum,".")) {
    $sum = $sum . ".-";
}
setlocale(LC_TIME, "fr_CH.utf8");
$date = htmlentities(strftime ( "%#d %B %Y", $compta->date ),ENT_COMPAT,$charset);
$today = htmlentities(strftime ( "%#d %B %Y", time() ),ENT_COMPAT,$charset);

$libele = htmlentities($compta->libele,ENT_COMPAT,$charset);

$sexe = $user->sexe;
if ($sexe != "na") {
    if ($sexe == "f") {
        $sexe = "Madame";
    } else if ($sexe == "m") {
        $sexe = "Monsieur";
    } else if ($sexe == "hf") {
        $sexe = "Monsieur et Madame";
    }
} else {
    $sexe = "";
}
$society = htmlentities($user->society,ENT_COMPAT,$charset);
$firstName = htmlentities($user->firstName,ENT_COMPAT,$charset);
$lastName = htmlentities($user->lastName,ENT_COMPAT,$charset);

$from = "";
if ($society != "") {
    $from .= $society . " ";
}
if ($sexe != "") {
    $from .= $sexe . " ";
}
if ($firstName != "") {
    $from .= $firstName . " ";
}
if ($lastName != "") {
    $from .= $lastName . " ";
}

?>
<h1><?=$date?></h1>
MIME-Version: 1.0
Content-Type: multipart/related; boundary="----=_NextPart_01C6355C.057E6270"

Ce document est une page Web ￠ fichier unique, ou fichier archive Web.  Si ce message est affich￩, votre navigateur ou votre ￩diteur ne prend pas en charge les fichiers archives Web.  T￩l￩chargez un navigateur qui prend en charge les archives Web, par exemple Microsoft Internet Explorer.

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit.htm
Content-Transfer-Encoding: quoted-printable
Content-Type: text/html; charset="us-ascii"

<html xmlns:v=3D"urn:schemas-microsoft-com:vml"
xmlns:o=3D"urn:schemas-microsoft-com:office:office"
xmlns:w=3D"urn:schemas-microsoft-com:office:word"
xmlns=3D"http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv=3DContent-Type content=3D"text/html; charset=3Dus-ascii">
<meta name=3DProgId content=3DWord.Document>
<meta name=3DGenerator content=3D"Microsoft Word 11">
<meta name=3DOriginator content=3D"Microsoft Word 11">
<link rel=3DFile-List href=3D"quit_fichiers/filelist.xml">
<link rel=3DEdit-Time-Data href=3D"quit_fichiers/editdata.mso">
<!--[if !mso]>
<style>
v\:* {behavior:url(#default#VML);}
o\:* {behavior:url(#default#VML);}
w\:* {behavior:url(#default#VML);}
.shape {behavior:url(#default#VML);}
</style>
<![endif]-->
<title>Si vous croyez &ecirc;tre trop petit pour faire quelque chose,</titl=
e>
<!--[if gte mso 9]><xml>
 <o:DocumentProperties>
  <o:Author>Myriam Ernst</o:Author>
  <o:Template>quittancedon_casa2.dot</o:Template>
  <o:LastAuthor>fepete</o:LastAuthor>
  <o:Revision>2</o:Revision>
  <o:TotalTime>1</o:TotalTime>
  <o:LastPrinted>2005-01-10T09:14:00Z</o:LastPrinted>
  <o:Created>2006-02-19T12:54:00Z</o:Created>
  <o:LastSaved>2006-02-19T12:54:00Z</o:LastSaved>
  <o:Pages>1</o:Pages>
  <o:Words>132</o:Words>
  <o:Characters>755</o:Characters>
  <o:Company>GOWEN FAMILY OFFICE</o:Company>
  <o:Lines>6</o:Lines>
  <o:Paragraphs>1</o:Paragraphs>
  <o:CharactersWithSpaces>886</o:CharactersWithSpaces>
  <o:Version>11.6568</o:Version>
 </o:DocumentProperties>
</xml><![endif]--><!--[if gte mso 9]><xml>
 <w:WordDocument>
  <w:DisplayHorizontalDrawingGridEvery>0</w:DisplayHorizontalDrawingGridEve=
ry>
  <w:DisplayVerticalDrawingGridEvery>0</w:DisplayVerticalDrawingGridEvery>
  <w:UseMarginsForDrawingGridOrigin/>
  <w:ValidateAgainstSchemas/>
  <w:SaveIfXMLInvalid>false</w:SaveIfXMLInvalid>
  <w:IgnoreMixedContent>false</w:IgnoreMixedContent>
  <w:AlwaysShowPlaceholderText>false</w:AlwaysShowPlaceholderText>
  <w:Compatibility>
   <w:SpaceForUL/>
   <w:BalanceSingleByteDoubleByteWidth/>
   <w:DoNotLeaveBackslashAlone/>
   <w:ULTrailSpace/>
   <w:DoNotExpandShiftReturn/>
   <w:AdjustLineHeightInTable/>
   <w:SelectEntireFieldWithStartOrEnd/>
   <w:UseWord2002TableStyleRules/>
  </w:Compatibility>
  <w:BrowserLevel>MicrosoftInternetExplorer4</w:BrowserLevel>
 </w:WordDocument>
</xml><![endif]--><!--[if gte mso 9]><xml>
 <w:LatentStyles DefLockedState=3D"false" LatentStyleCount=3D"156">
 </w:LatentStyles>
</xml><![endif]-->
<style>
<!--
 /* Font Definitions */
 @font-face
	{font-family:Times;
	panose-1:2 2 6 3 5 4 5 2 3 4;
	mso-font-charset:0;
	mso-generic-font-family:roman;
	mso-font-pitch:variable;
	mso-font-signature:536902279 -2147483648 8 0 511 0;}
@font-face
	{font-family:"Myriad Roman";
	panose-1:0 11 5 0 0 0 0 0 0 0;
	mso-font-charset:0;
	mso-generic-font-family:auto;
	mso-font-pitch:auto;
	mso-font-signature:0 0 0 0 0 0;}
 /* Style Definitions */
 p.MsoNormal, li.MsoNormal, div.MsoNormal
	{mso-style-parent:"";
	margin:0cm;
	margin-bottom:.0001pt;
	mso-pagination:widow-orphan;
	font-size:12.0pt;
	mso-bidi-font-size:10.0pt;
	font-family:Times;
	mso-fareast-font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:EN-GB;}
p.CasaAlianzabodycopy11, li.CasaAlianzabodycopy11, div.CasaAlianzabodycopy11
	{mso-style-name:"Casa Alianza body copy 11";
	mso-style-update:auto;
	margin-top:0cm;
	margin-right:0cm;
	margin-bottom:0cm;
	margin-left:70.9pt;
	margin-bottom:.0001pt;
	text-indent:-70.9pt;
	line-height:15.0pt;
	mso-line-height-rule:exactly;
	mso-pagination:widow-orphan;
	tab-stops:269.35pt;
	font-size:11.0pt;
	mso-bidi-font-size:10.0pt;
	font-family:"Myriad Roman";
	mso-fareast-font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:EN-GB;}
p.CasaAlianzatitre, li.CasaAlianzatitre, div.CasaAlianzatitre
	{mso-style-name:"Casa Alianza titre";
	mso-style-parent:"Casa Alianza body copy 11";
	mso-style-next:"Casa Alianza body copy 11";
	margin-top:0cm;
	margin-right:0cm;
	margin-bottom:0cm;
	margin-left:70.9pt;
	margin-bottom:.0001pt;
	text-indent:-70.9pt;
	line-height:24.0pt;
	mso-line-height-rule:exactly;
	mso-pagination:widow-orphan;
	tab-stops:269.35pt;
	font-size:18.0pt;
	mso-bidi-font-size:10.0pt;
	font-family:"Myriad Roman";
	mso-fareast-font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:EN-GB;
	font-weight:bold;
	mso-bidi-font-weight:normal;}
p.CasaAlianzasignature, li.CasaAlianzasignature, div.CasaAlianzasignature
	{mso-style-name:"Casa Alianza signature";
	mso-style-parent:"Casa Alianza body copy 11";
	mso-style-next:"Casa Alianza body copy 11";
	margin:0cm;
	margin-bottom:.0001pt;
	line-height:13.0pt;
	mso-line-height-rule:exactly;
	mso-pagination:widow-orphan;
	tab-stops:269.35pt;
	font-size:9.0pt;
	mso-bidi-font-size:10.0pt;
	font-family:"Myriad Roman";
	mso-fareast-font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:EN-GB;}
p.CasaAlianzabodycopy9, li.CasaAlianzabodycopy9, div.CasaAlianzabodycopy9
	{mso-style-name:"Casa Alianza body copy 9";
	mso-style-parent:"Casa Alianza signature";
	margin-top:0cm;
	margin-right:0cm;
	margin-bottom:0cm;
	margin-left:70.9pt;
	margin-bottom:.0001pt;
	text-indent:-70.9pt;
	line-height:13.0pt;
	mso-line-height-rule:exactly;
	mso-pagination:widow-orphan;
	tab-stops:269.35pt;
	font-size:9.0pt;
	mso-bidi-font-size:10.0pt;
	font-family:"Myriad Roman";
	mso-fareast-font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:EN-GB;}
 /* Page Definitions */
 @page
	{mso-footnote-separator:url("quit_fichiers/header.htm") fs;
	mso-footnote-continuation-separator:url("quit_fichiers/header.htm") fcs;
	mso-endnote-separator:url("quit_fichiers/header.htm") es;
	mso-endnote-continuation-separator:url("quit_fichiers/header.htm") ecs;}
@page Section1
	{size:21.0cm 842.0pt;
	margin:63.8pt 70.9pt 42.55pt 70.9pt;
	mso-header-margin:0cm;
	mso-footer-margin:0cm;
	mso-paper-source:0;}
div.Section1
	{page:Section1;}
-->
</style>
<!--[if gte mso 10]>
<style>
 /* Style Definitions */
 table.MsoNormalTable
	{mso-style-name:"Tableau Normal";
	mso-tstyle-rowband-size:0;
	mso-tstyle-colband-size:0;
	mso-style-noshow:yes;
	mso-style-parent:"";
	mso-padding-alt:0cm 5.4pt 0cm 5.4pt;
	mso-para-margin:0cm;
	mso-para-margin-bottom:.0001pt;
	mso-pagination:widow-orphan;
	font-size:10.0pt;
	font-family:Times;
	mso-bidi-font-family:"Times New Roman";
	mso-ansi-language:#0400;
	mso-fareast-language:#0400;
	mso-bidi-language:#0400;}
</style>
<![endif]--><!--[if gte mso 9]><xml>
 <o:shapedefaults v:ext=3D"edit" spidmax=3D"3074">
  <o:colormenu v:ext=3D"edit" fillcolor=3D"none" strokecolor=3D"none"/>
 </o:shapedefaults></xml><![endif]--><!--[if gte mso 9]><xml>
 <o:shapelayout v:ext=3D"edit">
  <o:idmap v:ext=3D"edit" data=3D"1"/>
 </o:shapelayout></xml><![endif]-->
</head>

<body lang=3DEN-US style=3D'tab-interval:8.5pt'>

<div class=3DSection1>

<p class=3DCasaAlianzatitre style=3D'margin-left:0cm;text-indent:0cm'><!--[=
if gte vml 1]><v:shapetype
 id=3D"_x0000_t75" coordsize=3D"21600,21600" o:spt=3D"75" o:preferrelative=
=3D"t"
 path=3D"m@4@5l@4@11@9@11@9@5xe" filled=3D"f" stroked=3D"f">
 <v:stroke joinstyle=3D"miter"/>
 <v:formulas>
  <v:f eqn=3D"if lineDrawn pixelLineWidth 0"/>
  <v:f eqn=3D"sum @0 1 0"/>
  <v:f eqn=3D"sum 0 0 @1"/>
  <v:f eqn=3D"prod @2 1 2"/>
  <v:f eqn=3D"prod @3 21600 pixelWidth"/>
  <v:f eqn=3D"prod @3 21600 pixelHeight"/>
  <v:f eqn=3D"sum @0 0 1"/>
  <v:f eqn=3D"prod @6 1 2"/>
  <v:f eqn=3D"prod @7 21600 pixelWidth"/>
  <v:f eqn=3D"sum @8 21600 0"/>
  <v:f eqn=3D"prod @7 21600 pixelHeight"/>
  <v:f eqn=3D"sum @10 21600 0"/>
 </v:formulas>
 <v:path o:extrusionok=3D"f" gradientshapeok=3D"t" o:connecttype=3D"rect"/>
 <o:lock v:ext=3D"edit" aspectratio=3D"t"/>
</v:shapetype><v:shape id=3D"_x0000_s1035" type=3D"#_x0000_t75" style=3D'po=
sition:absolute;
 margin-left:427.05pt;margin-top:.2pt;width:167.35pt;height:110.65pt;z-inde=
x:-3;
 mso-position-horizontal-relative:page;mso-position-vertical-relative:page'>
 <v:imagedata src=3D"quit_fichiers/image001.png" o:title=3D"logo_casaalianz=
a_CMYK2945"/>
 <w:wrap anchorx=3D"page" anchory=3D"page"/>
</v:shape><![endif]--><![if !vml]><span style=3D'mso-ignore:vglayout;positi=
on:
absolute;z-index:-3;margin-left:569px;margin-top:0px;width:223px;height:148=
px'><img
width=3D223 height=3D148 src=3D"quit_fichiers/image002.gif" v:shapes=3D"_x0=
000_s1035"></span><![endif]><span
lang=3DFR style=3D'mso-ansi-language:FR'>Quittance de don <o:p></o:p></span=
></p>

<p class=3DCasaAlianzabodycopy11><b><span lang=3DFR style=3D'font-size:18.0=
pt;
mso-bidi-font-size:10.0pt;mso-ansi-language:FR'>N&deg; <?=$compta->quittance?><o:p></o:p></sp=
an></b></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>Re&ccedil;u
de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><?=$from?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>la
somme de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp; </span>CHF <?=$sum?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>en
date du:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp=
;&nbsp;&nbsp; </span><?=$date?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>en
faveur de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp; </span>C=
asa
Alianza Suisse &#8211; <?=$libele?><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp; </span><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy9><span lang=3DFR style=3D'mso-ansi-language:=
FR'>Remarque:<span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Casa
Alianza Suisse peut retenir au maximum 5% du montant des dons destin&eacute=
;s
aux projets en Am&eacute;rique centrale pour financer ses projets de
sensibilisation et de r&eacute;colte de fonds.<o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11 style=3D'margin-left:0cm;text-indent:0cm'>=
<span
lang=3DFR style=3D'mso-ansi-language:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>Gen&egrave;ve,
le <?=$today?><o:p></o:p></span></p>

<p class=3DCasaAlianzasignature><span lang=3DFR style=3D'mso-ansi-language:=
FR'><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Pi=
erre-Yves
Binz <o:p></o:p></span></p>

<p class=3DCasaAlianzasignature><span lang=3DFR style=3D'mso-ansi-language:=
FR'><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Tr=
&eacute;sorier<o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11 style=3D'margin-left:0cm;text-indent:0cm'>=
<span
lang=3DFR style=3D'mso-ansi-language:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><!--[if gte vml 1]><v:shape id=3D"_x0000_s=
1037"
 type=3D"#_x0000_t75" style=3D'position:absolute;left:0;text-align:left;
 margin-left:427.05pt;margin-top:421pt;width:167.35pt;height:110.65pt;
 z-index:-2;mso-position-horizontal-relative:page;
 mso-position-vertical-relative:page'>
 <v:imagedata src=3D"quit_fichiers/image001.png" o:title=3D"logo_casaalianz=
a_CMYK2945"/>
 <w:wrap anchorx=3D"page" anchory=3D"page"/>
</v:shape><![endif]--><![if !vml]><span style=3D'mso-ignore:vglayout;positi=
on:
absolute;z-index:-2;left:0px;margin-left:569px;margin-top:561px;width:223px;
height:148px'><img width=3D223 height=3D148 src=3D"quit_fichiers/image003.g=
if"
v:shapes=3D"_x0000_s1037"></span><![endif]><!--[if gte vml 1]><v:line id=3D=
"_x0000_s1032"
 style=3D'position:absolute;left:0;text-align:left;z-index:-4;mso-wrap-edit=
ed:f;
 mso-position-horizontal-relative:page;mso-position-vertical-relative:page'
 from=3D"0,421pt" to=3D"595.3pt,421pt" strokeweight=3D".5pt">
 <v:stroke dashstyle=3D"1 1" endcap=3D"round"/>
 <w:wrap anchorx=3D"page" anchory=3D"page"/>
</v:line><![endif]--><![if !vml]><span style=3D'mso-ignore:vglayout;positio=
n:
absolute;z-index:-4;left:0px;margin-left:-1px;margin-top:560px;width:796px;
height:2px'><img width=3D796 height=3D2 src=3D"quit_fichiers/image004.gif" =
v:shapes=3D"_x0000_s1032"></span><![endif]><span
lang=3DFR style=3D'mso-ansi-language:FR'><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzatitre style=3D'margin-left:0cm;text-indent:0cm'><!--[=
if gte vml 1]><v:shape
 id=3D"_x0000_s1039" type=3D"#_x0000_t75" style=3D'position:absolute;margin=
-left:427.05pt;
 margin-top:.2pt;width:167.35pt;height:110.65pt;z-index:-1;
 mso-position-horizontal-relative:page;mso-position-vertical-relative:page'>
 <v:imagedata src=3D"quit_fichiers/image001.png" o:title=3D"logo_casaalianz=
a_CMYK2945"/>
 <w:wrap anchorx=3D"page" anchory=3D"page"/>
</v:shape><![endif]--><![if !vml]><span style=3D'mso-ignore:vglayout;positi=
on:
absolute;z-index:-1;margin-left:569px;margin-top:0px;width:223px;height:148=
px'><img
width=3D223 height=3D148 src=3D"quit_fichiers/image005.gif" v:shapes=3D"_x0=
000_s1039"></span><![endif]><span
lang=3DFR style=3D'mso-ansi-language:FR'>Quittance de don <o:p></o:p></span=
></p>

<p class=3DCasaAlianzabodycopy11><b><span lang=3DFR style=3D'font-size:18.0=
pt;
mso-bidi-font-size:10.0pt;mso-ansi-language:FR'>N&deg; <?=$compta->quittance?><o:p></o:p></sp=
an></b></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>Re&ccedil;u
de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span><?=$from?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>la
somme de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp; </span>CHF <?=$sum?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>en
date du:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp=
;&nbsp;&nbsp; </span><?=$date?><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>en
faveur de:<span style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp; </span>C=
asa
Alianza Suisse &#8211; <?=$libele?><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp; </span><o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy9><span lang=3DFR style=3D'mso-ansi-language:=
FR'>Remarque:<span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Casa
Alianza Suisse peut retenir au maximum 5% du montant des dons destin&eacute=
;s
aux projets en Am&eacute;rique centrale pour financer ses projets de
sensibilisation et de r&eacute;colte de fonds.<o:p></o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11 style=3D'margin-left:0cm;text-indent:0cm'>=
<span
lang=3DFR style=3D'mso-ansi-language:FR'><o:p>&nbsp;</o:p></span></p>

<p class=3DCasaAlianzabodycopy11><span lang=3DFR style=3D'mso-ansi-language=
:FR'>Gen&egrave;ve,
le <?=$today?><o:p></o:p></span></p>

<p class=3DCasaAlianzasignature><span lang=3DFR style=3D'mso-ansi-language:=
FR'><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Pi=
erre-Yves
Binz <o:p></o:p></span></p>

<p class=3DCasaAlianzasignature><span lang=3DFR style=3D'mso-ansi-language:=
FR'><span
style=3D'mso-tab-count:1'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbs=
p;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&=
nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; </span>Tr=
&eacute;sorier<o:p></o:p></span></p>

</div>

</body>

</html>

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/image001.png
Content-Transfer-Encoding: base64
Content-Type: image/png

iVBORw0KGgoAAAANSUhEUgAAAV0AAADnCAYAAAC9roUQAAAACXBIWXMAABcSAAAXEgFnn9JSAAAK
TWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanVN3WJP3Fj7f92UPVkLY8LGXbIEAIiOsCMgQ
WaIQkgBhhBASQMWFiApWFBURnEhVxILVCkidiOKgKLhnQYqIWotVXDjuH9yntX167+3t+9f7vOec
5/zOec8PgBESJpHmomoAOVKFPDrYH49PSMTJvYACFUjgBCAQ5svCZwXFAADwA3l4fnSwP/wBr28A
AgBw1S4kEsfh/4O6UCZXACCRAOAiEucLAZBSAMguVMgUAMgYALBTs2QKAJQAAGx5fEIiAKoNAOz0
ST4FANipk9wXANiiHKkIAI0BAJkoRyQCQLsAYFWBUiwCwMIAoKxAIi4EwK4BgFm2MkcCgL0FAHaO
WJAPQGAAgJlCLMwAIDgCAEMeE80DIEwDoDDSv+CpX3CFuEgBAMDLlc2XS9IzFLiV0Bp38vDg4iHi
wmyxQmEXKRBmCeQinJebIxNI5wNMzgwAABr50cH+OD+Q5+bk4eZm52zv9MWi/mvwbyI+IfHf/ryM
AgQAEE7P79pf5eXWA3DHAbB1v2upWwDaVgBo3/ldM9sJoFoK0Hr5i3k4/EAenqFQyDwdHAoLC+0l
YqG9MOOLPv8z4W/gi372/EAe/tt68ABxmkCZrcCjg/1xYW52rlKO58sEQjFu9+cj/seFf/2OKdHi
NLFcLBWK8ViJuFAiTcd5uVKRRCHJleIS6X8y8R+W/QmTdw0ArIZPwE62B7XLbMB+7gECiw5Y0nYA
QH7zLYwaC5EAEGc0Mnn3AACTv/mPQCsBAM2XpOMAALzoGFyolBdMxggAAESggSqwQQcMwRSswA6c
wR28wBcCYQZEQAwkwDwQQgbkgBwKoRiWQRlUwDrYBLWwAxqgEZrhELTBMTgN5+ASXIHrcBcGYBie
whi8hgkEQcgIE2EhOogRYo7YIs4IF5mOBCJhSDSSgKQg6YgUUSLFyHKkAqlCapFdSCPyLXIUOY1c
QPqQ28ggMor8irxHMZSBslED1AJ1QLmoHxqKxqBz0XQ0D12AlqJr0Rq0Hj2AtqKn0UvodXQAfYqO
Y4DRMQ5mjNlhXIyHRWCJWBomxxZj5Vg1Vo81Yx1YN3YVG8CeYe8IJAKLgBPsCF6EEMJsgpCQR1hM
WEOoJewjtBK6CFcJg4Qxwicik6hPtCV6EvnEeGI6sZBYRqwm7iEeIZ4lXicOE1+TSCQOyZLkTgoh
JZAySQtJa0jbSC2kU6Q+0hBpnEwm65Btyd7kCLKArCCXkbeQD5BPkvvJw+S3FDrFiOJMCaIkUqSU
Eko1ZT/lBKWfMkKZoKpRzame1AiqiDqfWkltoHZQL1OHqRM0dZolzZsWQ8ukLaPV0JppZ2n3aC/p
dLoJ3YMeRZfQl9Jr6Afp5+mD9HcMDYYNg8dIYigZaxl7GacYtxkvmUymBdOXmchUMNcyG5lnmA+Y
b1VYKvYqfBWRyhKVOpVWlX6V56pUVXNVP9V5qgtUq1UPq15WfaZGVbNQ46kJ1Bar1akdVbupNq7O
UndSj1DPUV+jvl/9gvpjDbKGhUaghkijVGO3xhmNIRbGMmXxWELWclYD6yxrmE1iW7L57Ex2Bfsb
di97TFNDc6pmrGaRZp3mcc0BDsax4PA52ZxKziHODc57LQMtPy2x1mqtZq1+rTfaetq+2mLtcu0W
7eva73VwnUCdLJ31Om0693UJuja6UbqFutt1z+o+02PreekJ9cr1Dund0Uf1bfSj9Rfq79bv0R83
MDQINpAZbDE4Y/DMkGPoa5hpuNHwhOGoEctoupHEaKPRSaMnuCbuh2fjNXgXPmasbxxirDTeZdxr
PGFiaTLbpMSkxeS+Kc2Ua5pmutG003TMzMgs3KzYrMnsjjnVnGueYb7ZvNv8jYWlRZzFSos2i8eW
2pZ8ywWWTZb3rJhWPlZ5VvVW16xJ1lzrLOtt1ldsUBtXmwybOpvLtqitm63Edptt3xTiFI8p0in1
U27aMez87ArsmuwG7Tn2YfYl9m32zx3MHBId1jt0O3xydHXMdmxwvOuk4TTDqcSpw+lXZxtnoXOd
8zUXpkuQyxKXdpcXU22niqdun3rLleUa7rrStdP1o5u7m9yt2W3U3cw9xX2r+00umxvJXcM970H0
8PdY4nHM452nm6fC85DnL152Xlle+70eT7OcJp7WMG3I28Rb4L3Le2A6Pj1l+s7pAz7GPgKfep+H
vqa+It89viN+1n6Zfgf8nvs7+sv9j/i/4XnyFvFOBWABwQHlAb2BGoGzA2sDHwSZBKUHNQWNBbsG
Lww+FUIMCQ1ZH3KTb8AX8hv5YzPcZyya0RXKCJ0VWhv6MMwmTB7WEY6GzwjfEH5vpvlM6cy2CIjg
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

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/image002.gif
Content-Transfer-Encoding: base64
Content-Type: image/gif

R0lGODlh3wCUAHcAMSH+GlNvZnR3YXJlOiBNaWNyb3NvZnQgT2ZmaWNlACH5BAEAAAAALBsAHgCg
AFIAhwAAAAAAAAAAqgAA/wAmkQAzqgBVVQBIbgBVmQBIkQBMmQBEmQBVnQBMnQBRnQBZnQBViABE
iABRpgBXrgBTpABVpgBUpwBctQBVqQBRogBTpQBYrwBTpwBMogBSpABTpgBVqgBVpABXrQBUpgBV
qABTogBXrABUqABWrABWqQBVogBWqABRoABYrgBWqgBUqQBSpgBSowBWqwBSpQBZrwBZsQBVpwBU
pABZsABYsABRowBdogBUpQBXqwBdswBWrQBatABZsgBasgBaswBctgBRqgBItwBeugBMpgBRpABX
qABWpwBevABduQBbtgBctwBdugBcuABVqwBUowBbswBYsQBRpQBEqgBXpgBbtQBEuwBbtABTowBT
oABVuwBVpQBTqABduABeuwBXqgBXrwBYrQBRrgBZqgBZqABOoABZpgBVrgBSogBXpABUqgBXogBd
qgBVrABZrgBdtQBVoABasQBZrABukQBmmQBmuwBmqgButwBu2QBhwABhwQBixACRtwCqqgCq/wD/
AAD//wECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwj/AAEIHEiwoMGDCBMqXMiwocOHECNKnEix
osWLAytYuIAhA8aPIEOK/KhhA4UNHEaqXMmy5YYPFj5saEmzpk2IIkaQ0EBixISbQIMCtYDBBIUQ
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
KCKyJ2uyI8ukJUuyKpuyVBEQADs=

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/image003.gif
Content-Transfer-Encoding: base64
Content-Type: image/gif

R0lGODlh3wCUAHcAMSH+GlNvZnR3YXJlOiBNaWNyb3NvZnQgT2ZmaWNlACH5BAEAAAAALBsAHgCg
AFIAhwAAAAAAAAAAqgAA/wAmkQAzqgBVVQBIbgBVmQBIkQBMmQBEmQBVnQBMnQBRnQBViABEiABZ
nQBRpgBXrgBTpABVpgBUpwBctQBVqQBRogBTpQBYrwBTpwBMogBSpABTpgBVqgBVpABXrQBUpgBV
qABTogBXrABUqABWrABWqQBVogBWqABRoABYrgBWqgBUqQBSpgBSowBWqwBSpQBZrwBZsQBVpwBU
pABZsABYsABRowBdogBUpQBXqwBdswBWrQBatABZsgBasgBaswBctgBRqgBItwBeugBMpgBRpABX
qABWpwBevABduQBbtgBctwBdugBcuABVqwBUowBbswBYsQBRpQBEqgBXpgBbtQBEuwBbtABTowBT
oABVuwBVpQBTqABduABeuwBXqgBXrwBYrQBRrgBZqgBZqABOoABZpgBVoABVrgBSogBXpABUqgBX
ogBdqgBVrABZrgBdtQBasQBZrABukQBmmQBmuwBmqgButwBu2QBhwABhwQBixACRtwCqqgCq/wD/
AAD//wECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwj/AAEIHEiwoMGDCBMqXMiwocOHECNKnEix
osWLAytYuIAhA8aPIEOK/KhhA4UNHEaqXMmy5YYPFj5saEmzpk2IIkaQ0EBixISbQIMCtYDBBIUQ
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
H8ukIQuyJluyVBEQADs=

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/image004.gif
Content-Transfer-Encoding: base64
Content-Type: image/gif

R0lGODlhHAMCAHcAMSH+GlNvZnR3YXJlOiBNaWNyb3NvZnQgT2ZmaWNlACH5BAEAAAAALAEAAQAZ
AwEAgAAAAAAAAAImDIynyesNn4x02oqvznz7Dn5iSI5miZ5qyq5uC79yTM92jd96ThUAOx==

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/image005.gif
Content-Transfer-Encoding: base64
Content-Type: image/gif

R0lGODlh3wCUAHcAMSH+GlNvZnR3YXJlOiBNaWNyb3NvZnQgT2ZmaWNlACH5BAEAAAAALBsAHgCg
AFIAhwAAAAAAAAAAqgAA/wAmkQAzqgBVVQBIbgBVmQBIkQBMmQBEmQBVnQBMnQBRnQBZnQBViABE
iABRpgBXrgBTpABVpgBUpwBctQBVqQBRogBTpQBYrwBTpwBMogBSpABTpgBVqgBVpABXrQBUpgBV
qABTogBXrABUqABWrABWqQBVogBWqABRoABYrgBWqgBUqQBSpgBSowBWqwBSpQBZrwBZsQBVpwBU
pABZsABYsABRowBdogBUpQBXqwBdswBWrQBatABZsgBasgBaswBctgBRqgBItwBeugBMpgBRpABX
qABWpwBevABduQBbtgBctwBdugBcuABVqwBUowBbswBYsQBRpQBEqgBXpgBbtQBEuwBbtABTowBT
oABVuwBVpQBTqABduABeuwBXqgBXrwBYrQBRrgBZqgBZqABOoABZpgBVrgBSogBXpABUqgBXogBd
qgBVrABZrgBdtQBVoABasQBZrABukQBmmQBmuwBmqgButwBu2QBhwABhwQBixACRtwCqqgCq/wD/
AAD//wECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwEC
AwECAwECAwECAwECAwECAwECAwECAwECAwECAwECAwj/AAEIHEiwoMGDCBMqXMiwocOHECNKnEix
osWLAytYuIAhA8aPIEOK/KhhA4UNHEaqXMmy5YYPFj5saEmzpk2IIkaQ0EBixISbQIMCtYDBBIUQ
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
JUuyIaukIyuyKHuyVBEQADs=

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/header.htm
Content-Transfer-Encoding: quoted-printable
Content-Type: text/html; charset="us-ascii"

<html xmlns:v=3D"urn:schemas-microsoft-com:vml"
xmlns:o=3D"urn:schemas-microsoft-com:office:office"
xmlns:w=3D"urn:schemas-microsoft-com:office:word"
xmlns=3D"http://www.w3.org/TR/REC-html40">

<head>
<meta http-equiv=3DContent-Type content=3D"text/html; charset=3Dus-ascii">
<meta name=3DProgId content=3DWord.Document>
<meta name=3DGenerator content=3D"Microsoft Word 11">
<meta name=3DOriginator content=3D"Microsoft Word 11">
<link id=3DMain-File rel=3DMain-File href=3D"../quit.htm">
<![if IE]>
<base href=3D"file:///C:\268A6194\quit_fichiers\header.htm"
id=3D"webarch_temp_base_tag">
<![endif]><!--[if gte mso 9]><xml>
 <o:shapedefaults v:ext=3D"edit" spidmax=3D"3074">
  <o:colormenu v:ext=3D"edit" fillcolor=3D"none" strokecolor=3D"none"/>
 </o:shapedefaults></xml><![endif]-->
</head>

<body lang=3DEN-US>

<div style=3D'mso-element:footnote-separator' id=3Dfs>

<p class=3DMsoNormal><span lang=3DEN-GB><span style=3D'mso-special-characte=
r:footnote-separator'><![if !supportFootnotes]>

<hr align=3Dleft size=3D1 width=3D"33%">

<![endif]></span></span></p>

</div>

<div style=3D'mso-element:footnote-continuation-separator' id=3Dfcs>

<p class=3DMsoNormal><span lang=3DEN-GB><span style=3D'mso-special-characte=
r:footnote-continuation-separator'><![if !supportFootnotes]>

<hr align=3Dleft size=3D1>

<![endif]></span></span></p>

</div>

<div style=3D'mso-element:endnote-separator' id=3Des>

<p class=3DMsoNormal><span lang=3DEN-GB><span style=3D'mso-special-characte=
r:footnote-separator'><![if !supportFootnotes]>

<hr align=3Dleft size=3D1 width=3D"33%">

<![endif]></span></span></p>

</div>

<div style=3D'mso-element:endnote-continuation-separator' id=3Decs>

<p class=3DMsoNormal><span lang=3DEN-GB><span style=3D'mso-special-characte=
r:footnote-continuation-separator'><![if !supportFootnotes]>

<hr align=3Dleft size=3D1>

<![endif]></span></span></p>

</div>

</body>

</html>

------=_NextPart_01C6355C.057E6270
Content-Location: file:///C:/268A6194/quit_fichiers/filelist.xml
Content-Transfer-Encoding: quoted-printable
Content-Type: text/xml; charset="utf-8"

<xml xmlns:o=3D"urn:schemas-microsoft-com:office:office">
 <o:MainFile HRef=3D"../quit.htm"/>
 <o:File HRef=3D"image001.png"/>
 <o:File HRef=3D"image002.gif"/>
 <o:File HRef=3D"image003.gif"/>
 <o:File HRef=3D"image004.gif"/>
 <o:File HRef=3D"image005.gif"/>
 <o:File HRef=3D"header.htm"/>
 <o:File HRef=3D"filelist.xml"/>
</xml>
------=_NextPart_01C6355C.057E6270--
