<?php

/***********************************************
* File      :   onlineakte.php
* Project   :   PHP-Push
* Descr     :   This backend is based on
*               'BackendCalDAV' by Jean-Louis
*               Dupond and implements an
*               OnlineAkte interface
*
* Created   :   07.09.2012
*
* Copyright 2012 Jean-Louis Dupond (BackendCalDAV)
* Copyright 2012 Matthias L�ling
************************************************/

require_once("backend/onlineakte/config.php");
require_once("backend/onlineakte/IdentityHMAC.php");
require_once("backend/onlineakte/NewTokenData.php");
include_once('lib/default/diffbackend/diffbackend.php');
include('httpful.phar');
define("FEHLER_NICHT_FREIGESCHALTET", "freigeschaltet"); // Produkt oder SB nicht freigeschaltet
define("FEHLER_NICHT_ANGEMELDET", "ist nicht angemeldet"); // Kanzlei / Datenbank ist nicht angemeldet
define("FEHLER_NICHT_REGISTRIERT", "ist nicht registriert"); // Kanzlei / Datenbank ist nicht registriert
define("FEHLER_FALSCHES_KENNWORT", "Falscher Name oder falsches Kennwort");
define("FEHLER_FALSCHES_KENNWORT_2", 'Benutzer / Kennwort ung');

class OnlineakteException extends HTTPReturnCodeException {

	protected $httpHeaders = array('Retry-After: 70');

	public function __construct($message = "", $code = 0, $previous = NULL, $logLevel = false) {
        if ($code) {
			$this->httpReturnCode = $code;
        }
		parent::__construct($message, (int) $code, $previous, $logLevel);
	}

}

class BackendOnlineAkte extends BackendDiff {

	private $_collection = array();
	private $_username;
	private $_usernameRest;
	private $_frnrSuffix; // Wird an die FrNr angehängt, ohne Umlaute usw. Beispiel: ml@@Kanzlei_L__ling@@MATTHIASL
	private $_user;
	private $_kuerzel;
	private $_kanzlei;
	private $_datenbank;
	private $_passwort;
	private $_passwortRest;
	private $_mitarbeiter = array(); // Kuerzel
    private $_relayUrl;
	private $_baseUrlTermine;
	private $_baseUrlTodos;
    private $_securityGatewayUrl;
    // Die $_connectorKanzleien können zu einem späteren Zeitpunkt wieder entfernt werden
    // private $_connectorKanzleien = array('mltest', 'legiteamgmbh2', 'stephan korb', 'steinbock-partner', 'helpdesktermine', 'linnemann', 'advocateassociate', 'kanzlei krone', 'meyer & frey', 'meyer &amp; frey', 'kanzleiskks', 'kanzlei-qlb', 'tietje', 'kanzlei boehling', 'wns2015', 'e&h', 'e&amp;h', 'atticus');
    private $_connectorKanzleien = array('mltest', 'legiteamgmbh2', 'stephan korb', 'steinbock-partner', 'helpdesktermine', 'advocateassociate', 
        'kanzlei krone', 'kanzleiskks', 'kanzlei-qlb', 'Tietje', 'tietje', 'kanzlei boehling', 'WNS2015', 'wns2015', 'e&h', 'E&amp;H', 'E&H', 'e&amp;h', 
        'atticus', 'ra-holler', 'durach', 'KoppundPartner', 'KoppPartner', 'ystest', 'rechtsanwaelte-telgte', 'RSCW', 'Kanzlei Vlachou', 
        'schwertle & schwertle', 'schwertle &amp; schwertle', 'birkholz', 'kanzleibischof', 'f-r-p', 'gabriele-hertel', 'osh', 'bboehle', 'raschmutz', 'anwalttaunusstein', 'ra-rausse', 
        'kanzlei kieper', 'rechtsanwalt-hoyer', 'rageissler', 'rechtsanwaltpauli', 'krüger-kirmess', 'chs rechtsanwaelte', 'kanzlei-thuemlein', 'raemoser', 
        'vertriebs-gmbh mitte', 'achour', 'rechtsanwaelte-passau', 'scholz', 'aih jens janssen', 'h.a.rai', 'sabineschwarzbo', 'vvs rechtsanwälte', 
        'annetterüb', 'thoennessen', 'akm172', 'selenberg-freitag', 'hoeherkramp', 'becker', 'wshp.law', 'lawcon', 'weigand &amp; keller', 'kanzlei-robe', 'krukowski', 'anwaltsbüro.paar', 
        'ratis', 'grrae', 'lenzeundpartner', 'familienanwalt-bs', 'ra-gregormaihoefer', 'eibelshaeuser', 'ght-kmb', 'hessel', 'mw-rechtsanwaelte', 'huS68161', 'kanzleien am hohenzollernring', 
        'schöffel', 'kanzlei-stoldt', 'ra-scheibe', 'rae-ehrenhainstrasse', 'kahlu', 'shp-recht-hechingen', 'halberechtsanwaelte', 'kdb.legal', 'pohlenmeister', 'conradlambert', 'wienberg', 
        'chs-rechtsanwaelte', 'kgsrw18', 'dmi-inkasso', 'freidl', 'kanzlei schullerus', 'koenigswall', 'braukmannkanzlei', 'bertramschulz', 'kanzlei vlachou', 
        'rawueller', 'ziems', 'guthke zickendraht', 'ademi', 'raproebsting', 'mmsfb', 'menzundpartner', 'kanzlei_nicklas', 'wigadi-koeln', 
        'nurkovic', 'muench', 'diller', 'beckermollenkott', 'krauseundpartner', 'kanzleiwittkop', 'drkroeger', 'swinkler', 'anwaltochsenfurt', 
        'anwalt-ru', 'rahoffmann', 'gubitz', 'blumenthal', 'wb42103', 'stumm', 'gks rechtsanwälte', 'kdu', 'ra-boes', 'wortmann', 'dubick', 'uebbert', 
        'rlsrechtsanwälte', 'blfpartner', 'metten-theissen', 'schoening', 'driemer', 'hebebrand', 'kpsonline', 'anwaeltin-hagen', 'thremer', 'ramoser', 
        'walther', 'drriemer', 'roehlke', 'theus-rechtsanwaelte', 'kabus', 'rechteffizient', 'a-k-t', 'freundlich-kollegen', 'woelbert', 'wr-kanzlei', 
        'wetter', 'schmelterschott', 'rapodewin', 'ponti.legal', 'KANZLEI-KANTEKIN', 'albrecht', 'ra-maschke', 'vonderinger', 'herrmann-weik', 'pdpartner', 
        'kschmidt', 'XXXhenties &amp; kollegen', 'sundermann', 'nbh-strafrecht', 'klostermann', 'syring', 'lisa_chiarelli', 'ssk', 'kanzlei_koerner', 
        'rechtsanwaeltinhuhs', 'hechmat', 'lesser', 'rechtsanwalt-beslenmis', 'barkhoff-partner', 'lang und menger rechtsanwälte partnerschaft', 
        'dohrmann', 'dietrich', 'lt_michael', 'kerstinnierling', 'd.kaiser', 'strafrechtlerin', 'kanzlei-breitbarth', 'w2wolff', 'law-con, oberusel', 
        'meiss-rechtsanwaelte', 'esser-esser', 'ra-schmelzer', 'fhlaw', 'redmer-rupp', 'rechtsinformer', 'rabaertschi', 'susanne-helweg', 
        'raevolmer_droeing', 'anwaltshaus gladenbach','petereit', 'kanzlei49', 'ra grotenhöfer', 'ruether', 'graf-schaefer', 'goldmann', 'axelvogt', 
        'bbp-essen', 'karl-xander', 'hölz knarr solf', 'meyer & frey', 'meyer &amp; frey', 'botsaris', 'mmr', 'pohlenmeister', 'woelky', 'hessel', 
        'milosevic', 'hea-rechtsanwalt', 'stahl', 'kanzlei-scaglione', 'schrey', 'buerorichter', 'kanzleiamcitytor', 'kanzlei-bussian', 'inesschwarz', 'koll-hoefer', 
        'kbm', 'tieben', 'henties & kollegen', 'henties &amp; kollegen', 'kanzkleiws', 'kaw-detmold', 'raewintermann', 'gennrich', 'rain-dhl', 'immobilienkanzle', 
        'ra-schwenger', 'erbrechtkontor', 'carstenhendrych', 'bsl', 'svaraknis', 'bfs-anwaelte', 'kanzleilanga', 'kowal', 'kanzlei_kordes', 'krettler', 
        'dka-kanzlei', 'boehmker', 'tup', 'anwalt-baecker'
    );
    private $_spk10Kanzleien = array('45746','150256','156564','12345678','51452711','123456789','2025061223',' kanzlei-meer',' ra_oeztop','01telefon02','178/11','2fe59tj45udmxoj','360grad','42dbs','42dbs_dev','a+k','aagv-duesseldorf','abarwitzki', 
'abeler-meyerhoff','abilita','ablass','abraham','abr-anwaelte','abshamburg','acjuris','adelmannohlhoff','adiuvo','adomeit natarajan und partnerg','adomeit-natarajan','adrianundbecker','advo','advocar','advocar-test', 
'advocat','advocatio','advoinfo','advomeditax','advo-ott','advo-schnegans','advothek','advoware','advoware','advowaredev','advo-web gmbh','advoweb2011','advw9999000001','adwoware','aep','affr','ag-test','agthe','agv-ge', 
'agv-luedenscheid','agv-olpe','agv-paderborn','ahrensvolmerdroeing','aich','aih','ailegaltwin','aim-online','air-rechtsanwaltsgesellschaft mbh','akademie','akk','akkaya-dimarco','akkoeln','akkrz','akktest','akktest2', 
'albrechtkk','alexandermeister','alextest','alkan','al-test','altug','altug&kollegen','ameri','ameri janson ondrasik rechtsanwälte partg mbb','am-hellweg','amnisex 4.1.18 lu','andreas.will','andreas123','andreasbucher', 
'andreasgross','andreash','andreashuelskoetter','andreasplum','anwaeltin-isermann','anwaeltinnen-hd','anwaeltin-wolf','anwaltalfeld','anwalt-aue','anwalt-brunsbuettel','anwalt-do','anwältehaus','anwalt-friedrich','anwalt-hasson', 
'anwalt-ilaender','anwalt-in-hamm','anwalt-kamenz','anwaltkoehler','anwaltkuhn','anwalt-linke','anwalt-mansius','anwalt-nordenstadt','anwalt-oschersleben','anwaltsbuero-leipzig','anwaltsbuero-lpz','anwaltsbüro silke waterschek', 
'anwaltsdatenbank','anwaltsdatenbank i','anwaltsdatenbankdrs','anwaltskanzlei','anwaltskanzlei arslan','anwaltskanzlei baumbach','anwaltskanzlei czasch','anwaltskanzlei sohn','anwaltskanzlei volante','anwaltskanzleiakay', 
'anwaltskanzlei-brake','anwaltskanzlei-diem','anwaltskanzlei-drischmann','anwaltskanzleieckel','anwaltskanzleien','anwaltskanzleifelixlinnenbrink','anwaltskanzleifuss','anwaltskanzlei-hoppe','anwaltskanzleilengtat', 
'anwaltskanzlei-luelsdorf','anwaltskanzlei-sande','anwaltskanzlei-sperling','anwaltskanzlei-urban','anwaltstag','anwaltstag2','anwaltsunion','anwalt-vogelsang','apelmeinrenkenlange','aposlegal', 
'arbeitgeberverband für den kreis olpe e.v.','arbeit-recht','arecht','arikansahin','arikan-sahin','arslan-rechtsanwalt','arta-rama','as-36','ashraf','asibasgarzoei','asp-rechtsanwaelte','assbo','assekuranz-kuemper', 
'assmann','auf-dem-berge','augustin','aundp','autoclaim','aw','awmobile','awmobile2','az_rechtsanwalt','b.trilling','b2','ba16-rechtsanwaelte','badtke','baehr','baer_rechtsanwaelte','bagusch-driemecker','bagusche', 
'bahr-kurtenbach','balduin','balg','balkenhol rechtsanwälte','ballertest','bangel','bangewasert','bardelleundkollegen','bartlingschaefer','basedow','batrinu','bau, voss & collegen alte kennung','bau-coll','bauconsult', 
'bauer-ebner','bauerundkollegen','bauhei','bauindustrie_nord','baumhaekel','bbg','bbm','bbrae','beckepp','beckmann','bec-online','beeking-gestewitz','begrbe','behlersoulas','beimesche','beinhorn','beknazaryan', 
'beknazaryan-linden','belnea','belnea','benens','benesch-winkler','benjamin frank','bepiuletilovic','bereniceböhlo','berger-federenko','bergk','bergstraesser-wegmann','bernardkorn','bernd','bernius','berzdorf', 
'besenbinderhof60','bettinabirk','bezzegh','bfs','bg','bgw-rae','bhj','bhj24','bhlc','biebinger - wirtschaftskanzlei','biebinger-wirtschaftskanzleige','biesdorf-kram-partner','bilginabaci','binder','binninger', 
'biscas kurzke rechtsanwaelte','bisping','bkgpartner','bktro','bktrto','blank','blaszczyk','blaszczyk_insider','bleck','bleckkentgensfricke','blenkersdinklageewert','blenkersdinklageschneider','bloemke','blonigen', 
'bluem','blumberg','blumeundblume','blumlang','blumlang','bnt','bocionek','böcker hammecke weyer','bodenbach und schaefer','bodenrae','boeckel-uhlmann','boehm','boergers','boettcher','bogdahn','böhmker', 
'bolwindoktersgesp','bonnemann','bonn-recht','bookjans','borgert-borries','bowe','bph','brbobr','breckwoldt-recht','bredereck willkomm','bregal','brill-recht','brinkert','brinkmann & behr','brunner-law','brunscoll', 
'brust und bader','bsb-law','bskpartner','btr brandenburg','buchalik brömmekamp rechtsanwaltsgesellschaft mbh','buchalikbrömmekamp','buchholz','buchholzbensberg','budczinski','buder','buder fmb','budertest', 
'buergerstiftung herford','buettgenbach','bundprs','bupawonline','burgard','burghartz','burgmer rae','burow-legal','buschmann','busenthuer&banten','bwberfurt','bwbmainz','bwl','bwlc', 
'bwlc ra','bwlrn','bw-mosbach','bwp-borna','bwp-chemnitz','cakir ex - gesp.','capelle','cee','cellar','cenacom','cengiz','cf recht','changir','christianroentgen','christoph-eiser','christophhoppe','ciper','clausen&kollegen', 
'cleverlaw','closhenpartner','clpdus','clpr','clpr02','cmtgmbh','cn-advo','coface deutschland','comlegal','conrad','consecom','coprian','corsepius-haegele','cramm','csc-dresden','cvs-kanzlei','cwmarquardt', 
'czwikla','d.reimers','dalhoff','danielawernze','danielkroh','dannecker-lauren','datarea','dbr','dbr2022','dbrmbh','ddalegalde','decker & böse rechtsanwälte','dehne-hild','deinert-purk', 
'deisenbe','deligiannis','deltalaw','demo','de-neve','dennig','dg-legal','diekanzleikoeln','die-strafrechtlerin(gesperrt)','die-strafrechtlerin(gesperrt)','dietmarschade','digita-law','dillmann haab millich schaich', 
'dinkler','dittert','d-m79e-wapwe-48','doerkes','dolejardieter !! ex','domusjuris','doraluisaschnegans','doreen','dorothe-lanc','dorschner','douglasluther','dr. gerrit binz','dr. junge','dr. maus und kollegen','dr. prasse', 
'dr. saalfrank','dr. steffen wiederhold','dr. stüber pp.','dr.conring','dr.halbe','dr.stüber pp.','dr.stüber pp.','dr-bruns','drdehne','dr-drees','dr-hohmuth','drhul','drklostermann','drkruse','dröge-thülig','dr-riemer.de', 
'drschpfu','dr-tripp-ludwig','dr-tripp-ludwigx','drwtp','dsk','duerr','dwp-rag','eckhoe','ecr gmbh','ee-anwalt','eggert-und-zipfel','eggert-zipfel-leipzig','eggesiecker','eh','ehlers-feldmeier','ehlerslorenzen','ehrenfelder', 
'eikelau','ekp-kanzlei','elben','elbracht','elisabethgamroth','ellinghaus','ems-inkasso','enfors','eniskorkmaz','eoswald','erbrechtler','erfolgsanwälte','er-hh','erne-posch','eskowitz','esrechtsanwaelte','estorf josephs', 
'eue-schiewek','eundh','euronord','eva-aachen','evawkanz','evawkanzins','evawkanzprd','eve lokal','eventandlaw','f28anwalt','fachanwalt-lehmann','fachanwaltskanzlei gebauer','fachanwaltskanzlei gerd vogl', 
'fachkanzlei kraemer-schoenlaug','fachkanzlei-kraemer-schoenlaug','facius','fairpayshs','falz','familienrechtskanzleiwolf','farinato','fastlaw','fbgesp.','feldkamp','felgemacher','feonline','ferba-kanzlei','ferner & muster', 
'feuerhake','ff','fglaw','fion.team','fiopieterek','fischerundguenes','fitik-yarisan','fliga','floether-wissing','florianweiss','fmb','fmk-recht','forderungsmanagement','forgbert','franke&ernst','frank-rae','frankstege', 
'franz und heinke','freiwang_insider','frerichs','friedrich10787','frings','fringspartners','fritz','fritz & partner','fritz& partner','fritz&partner','fritz-fachanwaelte','fritz-fachnwaelte.de','fuchsundkollegen', 
'fuerst-kanzlei','fuhrmann','fum legal','fusbahn','fut','fzfrechtsanwaelte','gaede','gaertner','gahrsudhoff','garlipp','gd-legal','gdl-frankfurt','gdl-rechtsabteilung','geiger','geitz','geitzrienecker','georg v. brevern', 
'georg.conrad@gmx.de','geraats&kollegen','gerdhue','gerhardhue','gerhardhuelskoetter','gerlach_und_mieland','germer','geyerliess','gh legal','ghhup','gholm','giesen','gilot','gippert-klein','g-k-t','glaeser-rechtsanwalt', 
'glathe','gloeserhechmat','glw','godo-brehsan','goecmen','goersdorf-md','goertz','goettler','goldberg','goldbergraewuppertal','goldenstein collections','goldentech test','goldhammer-selter','goldschmidt','göttler','göttling', 
'götz drosdek-otten','graef-centorbi','graeper','g-recht','greffenius-law','greinwald','greus','grolle-kleinmueller','gromert','gröninger','grothe-jebing','gschnerwitzki','gs-law','gtcmlg','gtkginstertheisklein', 
'guenter-winkler','gundacker','gundj','gunnar.adam','gursch','gursch','gütter','haaseragmbh','haasköhler','habatkhider','hacksteinreuter','hafenkanzlei-dortmund','hagemann','halbrecht', 
'halm & preßer','halmcollegen','hambach','hammermeister','handelsverband bayern','handelsverbandtrier','hannesinsider','hannestest','hansen','hansen riebegesp.','hansjuergenruppert','hape','harea','harimech-babic', 
'harimech-babic','hartig','haslerkinold','hassenpflug','hauser','hausteinrossier','hecker','heckundkollegen','heer-sturm','hefer streppel und partner','heidelaw','heidelberg-fachanwaelte','heidemann','heineberg','heinicke', 
'hemminghemming','hempel','hendrik hunold','henningsen','hentschel','heppt','hermandung','hermann jobe','hermann+werner','hermanncc','hermannkollegen','herms','hernichel','herrmann','herrmann','herrmann-mey','hesse-baurecht', 
'hettinga','heumueller','heyken','hfb-ka','hh$123','hilbert kampf sgumin rae pg','hildenbrand','hilfsmittel-recht','hilleke','hillenbrand','hintzler','hkb','hkb','hko','hkpartner','hk-recht','hkt01','hll','hll','hmglaremin', 
'hmk','hmmw','ho-anwaltskanzlei','hoeck-nb','hoeherrae','hoeh-koenigsamen','hoehmann-gudensberg','hoernlein-rae','hoffmannpartner','hoffmannvonwolffersdorff','höflesauer','hofsaess-partner','hohebleichen21','holgerley', 
'holgerreichert','hornundpartner','hpf-recht','hrelaw','hshb-duesseldorf','hsp-recht_bremen','htlra','hubert-parter','hubert-partner','huebner','huelskoetter','huelskoetterundpartner','huepart','hundertmarkrohde', 
'hundeshagenthiele','hup','hupp','huprz','hzp-kanzlei','i.emre','ibert & partner','idoverbandev','ikfreiburg','ilc','ilex','ilkgülbingölay','immobilienrecht-frankfurt','ina reese','inde','info@lang-law.de', 
'info@ra-rienaecker-taesch.de-g','ingenohl','ingogasser','ingothews','inkassostumpf','inselgruppe','interconhold','in-west-halle','iovos','ipcl','isar-legal','iundp','iurfriend-kanzlei','jaegler','jagenburg','jagusch', 
'jahnorthmann','jakab-recht.de','jakob-klein','jakobsguilleaume','jakobsmeier-broering','jakobspindler','jan','jan','jan.broecker','jan123456','jannes','janson','janssen_staudacher','jelben','jenhup','jensdecieux','jensotte', 
'jensvalenteijn','jessen','jfg','jfg2004','jfso1','jhs legal','jj','jmb-law','jmc.legal','jmwessels','joep_gl','joerei','jokitest','jonasjung','jonzy','jörn gaebell','jost','jota rechtsanwälte','jung-yeon sohn','jupus-online', 
'jurasolutions','jurpex','jusmed','justinlegaltech','justinlegaltech1','justitia partgmbb','k&i','k.jaeger','kaä_hh','kabuer','kag','kag-heidelberg','kai malte lippke','kaimaltelippke','kaldewei','kanack','kanrecon','kanzlei', 
'kanzlei altes zollamt','kanzlei am aerztehaus','kanzlei am weinberg','kanzlei andreas schwartmann','kanzlei as','kanzlei bauer','kanzlei boje','kanzlei buck','kanzlei dr. mandelkow','kanzlei föcking','kanzlei foecking', 
'kanzlei für erbrecht elke nico','kanzlei gröger','kanzlei grueneberg','kanzlei j. schaller','kanzlei kahle','kanzlei klar','kanzlei könig geperrt pj 12.12','kanzlei krahmer','kanzlei kuehnemund', 
'kanzlei liebeton','kanzlei limburg','kanzlei lindwehr','kanzlei luv','kanzlei mandelkow','kanzlei mandic gesp.','kanzlei nottelmann','kanzlei paul','kanzlei saftiuc','kanzlei sauer', 
'kanzlei schneckener','kanzlei schulz','kanzlei seiler','kanzlei to44','kanzlei toben','kanzlei villa verde','kanzlei wolters','kanzlei zeller (lehmann) gespe','kanzlei zywicki','kanzlei.rode','kanzlei.saglam','kanzlei.speth', 
'kanzlei.will','kanzlei_d&d','kanzlei_gebhard','kanzlei_heinrich','kanzlei_kenkel','kanzlei_martin','kanzlei_peter','kanzlei_portugall','kanzlei_stadt-re','kanzlei_wambach_walter','kanzlei_winkler','kanzleiabaci', 
'kanzlei-abelein','kanzleiack','kanzlei-am-europaplatz','kanzleiamkloster','kanzleiamlandratspark','kanzleiammarkt','kanzlei-am-standesamt','kanzlei-am-steinmarkt','kanzlei-bantis','kanzlei-bartel','kanzlei-becker', 
'kanzleibegas.de','kanzleibg','kanzlei-biehl','kanzlei-bleck','kanzlei-bmeyer','kanzleiboden','kanzlei-bouffleur','kanzlei-bph','kanzlei-brake','kanzlei-brandt','kanzleicb','kanzlei-depenbrock','kanzlei-dh','kanzlei-dittmer', 
'kanzlei-dogan','kanzlei-dorfmark','kanzlei-du','kanzleiecelioglu','kanzlei-elbregion','kanzleiengel','kanzlei-engelmann','kanzlei-erven','kanzlei-esb','kanzleiessergesp.','kanzlei-fehlberg','kanzlei-feldner','kanzleifeller', 
'kanzleifischer','kanzleifoecking','kanzlei-fp','kanzlei-franziska-germer','kanzlei-fte','kanzlei-gebert','kanzlei-gebhard','kanzlei-gerling','kanzlei-goebel','kanzlei-goekkaya','kanzleigoldbach','kanzleigörtlitz', 
'kanzlei-gros','kanzleihaus','kanzlei-heidicker','kanzlei-heidicker','kanzlei-heinemann','kanzlei-hessling','kanzleihofaecker','kanzleihs','kanzleihus','kanzlei-ick','kanzlei-jannsen','kanzlei-jhe','kanzlei-jurin', 
'kanzlei-kalmbach','kanzlei-karakas','kanzlei-karatas','kanzleikesting','kanzleikirchhellen','kanzlei-klein','kanzleiknott','kanzlei-koblenz','kanzlei-koenen','kanzlei-korumtas','kanzlei-kostka','kanzleikrahe', 
'kanzlei-kroeger','kanzleikurt','kanzlei-labisch','kanzlei-lachner','kanzlei-lauten','kanzlei-leis','kanzlei-litschner','kanzleilkk','kanzlei-lms','kanzlei-lms','kanzleiloewen','kanzlei-loos','kanzlei-manduzio', 
'kanzlei-martinvoss','kanzlei-md','kanzleimichaelis','kanzleimt','kanzlei-mudamm','kanzlei-mueller-hig','kanzlei-mww','kanzlei-na','kanzlei-nath','kanzlei-neumann','kanzleinittel','kanzleinolte','kanzleionline', 
'kanzleioster','kanzleipappelstrasse','kanzlei-pasquay','kanzlei-php','kanzlei-pochert','kanzleiqlb','kanzlei-rafiah','kanzleirbsp','kanzlei-recht','kanzleireck','kanzlei-redell','kanzleirheinsieg','kanzlei-ricker', 
'kanzleirinklin','kanzlei-rogalla','kanzleisadighi','kanzleisaglam','kanzlei-saglam','kanzlei-sasse','kanzleisbr','kanzleischeider','kanzlei-schiller','kanzleischiller_test','kanzlei-schlindwein','kanzlei-schluppkoweit', 
'kanzlei-schluppkoweit2','kanzlei-schmidt-siegl','kanzlei-schnittker','kanzlei-sh','kanzleisiemens','kanzleisimon','kanzlei-simon','kanzlei-simonis','kanzleisincarbasun','kanzlei-spethmann','kanzlei-ssr','kanzleistuhlmacher', 
'kanzleitaleb','kanzleitallen','kanzlei-thoma','kanzlei-tils','kanzlei-trautmann','kanzleiulrichbeckmann','kanzlei-vmb','kanzlei-vorlaufer','kanzlei-vts','kanzlei-wach','kanzlei-waid', 
'kanzlei-wellmann','kanzlei-werner','kanzlei-wettstein','kanzleiwieg','kanzlei-wilke','kanzlei-wirth','kanzleiwo','kanzleiws','kanzleiwsk','kanzlei-yagbasan','kanzleizeller','karami-legal','karl-heinz1967','karlokanzleier', 
'karlsruhe','karras','karstenklein','karsten-klein','karsten-roessler','karstrae','katharinakestler','katsanou','kawifu','kbb-heidelberg','kd-onlineakte','kebecker','kegler rechtsanwaelte','kellerkollegen','kemper','kempgens', 
'kestler','kettenbeil','kfamlada','kfv-verkehrsrecht','kg-f.de','kgonline','kgsrw182','khg','kieninger-kovacevic','kiepenkerl','kilinc-langwedel','kirchner&richrath','kirtsidis','kisters','kkm','klaus','klaus','klaus pampus', 
'klauspampus','kleemann','kleikamp','kleine','kleinlich und meier','kleinlich&meier%','kleinlichadvoweb','kleinlichmeier','kleinsorge-online','kleiser-kollegen','klepsch','klimek','klinger','kloetzer-eydt','klohs-luebeck', 
'klopsch-rae','kl-plauen','knapp','k-n-b','knebel','knigge','kniggenb','knpp','kochundlanghorst','koehn','koelschbach-partner','koetzfusbahn','kohnen','kohnend','kohnen-pauw','ko-kanzlei','koll-rechtsanwaelte', 
'kontakt@ra-krautz.de','kopietz','korn vitus','kornrechtsanwaelte','korumtas','kotz','kpb rechtsanwaltskanzlei','kpk','kraemer-schoenlau','krahmer','krautz','kreimer-kanzlei', 
'kreischer, ackermann & partner','krepcke','kretschmerrechtsanwaelte','kroh','kropfrehberger','krsc','krueger','ksg','k-s-p-p','ktrechtsanwalt','kuk','kulisch','kumdd','kundk','kurscheidt','kurscheidt','kuschnizki','kutzner', 
'laaser','laatsch230157','lachenauer','lachmund-law','lamprecht-rechtsanwaelte','langewittum','larshohnq','larsjaeschke','lawnourai','lawoffice grosskopf','law-rischk','lc-onlineakte','lcs-kanzlei','legalac','legalalliance', 
'legallinks','legiteam','legiteam','legiteam gmbh','legiteamgmbh','legiteamgmbh','legiteamgmbh1','legiteamsupport','lehmann','lehmann und partner','lehmannbehrens','lehrmann','lemketest','lena lange','lengers','lenssen','lenze', 
'lenzepartner','leonie-janssen','leppigundvosskamp','leschnigkollegen','letsche','letter-gerlach','lextm','lilalu','linkando api','linnemann','linussteinkugler','lipinski','lkm-ra','lob','lohkampteepe','lohmeyer','lohr-legal', 
'lola','lotz-schmidt','lpn','ls','lss-partner','lsteinkugler','lt_alexander','lt_aline','lt_andreas','lt_aswin','lt_beate','lt_borislav','lt_cezmi','lt_chantal','lt_christian','lt_claudia','lt_consiliari','lt_demo','lt_dirk', 
'lt_dominik','lt_doreen','lt_enablement','lt_evelyn','lt_fabian','lt_felix','lt_florian','lt_ilija','lt_iman','lt_jakob','lt_jens','lt_johann','lt_kristina','lt_lena','lt_lictest','lt_lukas','lt_lukasp','lt_maja','lt_manuel', 
'lt_marina','lt_martin','lt_martina','lt_mick','lt_nico','lt_oliver','lt_pen','lt_philipp','lt_rabia','lt_rasa','lt_sabrina','lt_stefanie','lt_susanne','lt_techleads','lt_thilo','lt_tristan','luchtenberg', 
'ludewig rechtsanwälte','ludwig','lutze gesp.','lwpmeissen','lw-recht','maa-kanzlei','macioszek','maekel-rechtsanwalt','mai&hohnq','maierundwintz','maklerware','maklerware','maliqi','malmström','marc.baldus', 
'marc-christopher-schmidt','markus goldbach','markus-buerger','markusgoldbach','martin & partner','martin-kaulbach','martinkurz','marxhausen','matthiasbiskupek','matzenpartner','maus-siebenhaar','mayerlaw','mayerundeuler', 
'mbruns','mbt-onlineakte','md471111','md-legal','medertmorsch','medialine','mediation-wetzlar','medizinrecht-hery','meikesteiner','meine-rechte','meissner','melzer + penteridis','memo-law','merker-bippus','merten','mertens', 
'meschkat und nauert','methodigy','mettenheim','meyer arbeitsrecht','mgy_ins','mgy_prod','mgy01','mh-medizinrecht','mhoffmann','mikralex','milleschewski-recht','minderjahn','mitschke','mltest2','mobile','möbus & schwaar', 
'modschiedler','moeller-rechtsanwaelte','moenkemeier','mohr rechtsanwälte','mohrmann','mohyla','momberg','mopa','morgenstern','motorrechte','moyal&simon','mpauly','mp-kanzlei','mq','mr-anwaelte','mrar','mrtest','msbh-hamburg', 
'mschaefer','msconcept','msh','msh rechtsanwaelte','mssb-kanzlei','mueller.legal','mueller-klein','muellernoethen','mueller-tegethoff','muff','müller_rechtsanwälte','müllerkörbel','mustermann','mustermann','mv.dortmund','mw', 
'mwn22','mww-rae','mydebitors','myktest','mzs','mzs rechtsanwälte','nancy-brehme','nath','netgocloud','netteonlineakte','neuerburg','neufeld','neugebauer','nicole-pfuhl','niehof','niemann-kanzlei','nieporte','nierfeld', 
'niermann-bock','niessalla','nill-boehm','nimrod-rechtsanwaelte','nk-kanzlei','nobach','nobach','noethelegal','noltemeier-niederl','nopper-vomschloss','nordhorn-','nordhorn-kuehlke','nordkirchen-familienrecht','nordwest-medien', 
'notarnet','notebook','notebookms','np-recht','nuemann+lang','nuemann-siebert','ochsenfeld','odcr','oehm-gießen','oetra j','oezdemir-reimholz','oezkan-law','oguzkorumtas','oklaw','oldenkottges.','olieber','onlineakte', 
'ordnungsrecht','ortmann','osb','ostadlegal','ostermaier','osthoffundschneppendahl','ostmeyer','ottiliabojo','palmberger','pantel','pantel2','pantel3','panthega','pascalwalterstps','paßmann','patentanwalt','pätzel witt', 
'paulscoerper','pausekuerschner','penkert','pense','persike','peterbuehner','peter-doerrenbaecher','petersteeg3','peterzwingel','pfau','pferdehirt','pfuelb','pfuhlschu','philippmaag','phyrr-dortmund','pieczynski','pihalealboth', 
'pili','pinarak','piper','pischke & wolfrum','plenovia','poesslundpollmann','poskeh1505','pothmann','praesentation','praesentation','prahl','präsentation','privateadvice','prof. dr. l. grosskopf ll.m.eu','prpg-18','ps', 
'psd-rechtsanwaelte','ps-verkehrsrecht','ra andreas holm','ra basedow','ra bauerfeind','ra bonk','ra budczinski','ra cst','ra dr. meurer','ra emmanuello','ra frank','ra golder','ra grimmelijkhuizen','ra hebinger','ra hessel', 
'ra howe','ra joachim prank','ra kowal','ra kruschina','ra lanius','ra m. budczinski','ra markus krüger','ra markus matzkeit','ra markus rüpke','ra michael brand','ra michalowski','ra prochnow','ra rebhan','ra schmied', 
'ra schmitz','ra schwartmann','ra sellmann','ra stefan frank','ra syring','ra tänzer schkeuditz','ra td','ra thormann','ra.grueneberg','ra.heusler','ra.uwe.mahnke','ra/stb strecker','ra_grema','ra_koberstein','ra_platz', 
'ra_voigt','ra0711','ra-agron.kelmendi','ra-akin','raalm','ra-anton-conrad','ra-awe','ra-bartholl','ra-bauerfeind','rabebris','rabecker','rabeckmann','ra-belgardt','ra-bernd-schaefer-hd','ra-beutel','rabisping','rabisping', 
'ra-bockelmann','ra-boether','ra-breckweg','rabrennecke','ra-brilla','ra-brueninghoff','ra-centmayer','ra-charalambis','rachrmi','ra-ciolek','raclaas','racohrs','ra-croset','radamm','radamm','rademacher','ra-diekmann-berenzen', 
'radietrich','ra-dimsic','ra-dogru','radona','ra-drlinden','rae','rae becker pp.','rae hilgemann & lattreuter','rae kreutz','rae wolff goebelgesp','rae wpk','raeb','raebbm','raebd','raebehrens','rae-benecke','raebergdolt', 
'raebergerwild','raebhw','rae-biege-fahl','rae-bleck','rae-brandt-weinreich','rae-breuerlehmann','rae-brockmeier','raectw','rae-drkeller','rae-essen','raegaedo1','raeisenmann','raekl','raekoeve','raeleinenweber', 
'rae-maier-piening','rae-meyer','rae-nau','raenegt','raenowi','raeoliverschmidt','raepn','rae-ruether-sippel','raeschof','rae-sfc','raesieperloesing','raespkk','raettf','raevoelker','rae-wigger','rae-wirmer','raeziegler', 
'raezippelius','rafal','rafederkiel','ra-feix','raferslev','rafluske','ra-freier','ra-gebhard','ra-geist','ragerstel','ra-goettler','ra-goetz','ragoldbeck','ragregor','ra-groenert','ragruenwald','ragundacker','ra-gurok', 
'ra-gz','ragzb','ra-heemeyer','ra-heermann','raheiermann','ra-heinz','raheldt','rahelmes','raherrlitz','raheusler','ra-hoedtke','ra-hofmann-regensburg','ra-hollenberg','ra-holstein','ra-holtus','rahook','rahuettner', 
'rahundertmark','ra-immokauf','rain sohn','rain sommer','rain-arlt','rainbailey','rain-bonn','raindd12555','rainer pesch','rain-familienrecht','rainhuelsebus','rain-klinkenberg-rais','rain-ruske','rainsasse','rainsboehme', 
'rainschneiders','rain-stephan-malak','raintahdenfarha','rainwinkler','rain-zimmer','rajahn','ra-jannack','rajanraschka','ra-janzen-dortmund','ra-jennings','ra-jenswahl','ra-jordan','rajung','rakaberthold','rakahlen', 
'ra-kaiser','ra-kanzlei-schneider','ra-karatas','ra-karger','rakarpinski','ra-kazak','ra-keskin','ra-keskin','rakessler','rakestler','ra-kirr','ra-kjf','ra-klingen','ra-knips','rakock','rakör','rakortmann','ra-kreimer', 
'rakro','rakschmitz3680','ra-lachner','ra-leitmann','raliko','ra-lomp','ramaibaum','ra-mandic','ramansius','ramatthias','ra-matussek','ramatzkeit','ramc','ra-mehlig','ra-meinhardt','rami','ramichalowski','ra-miller','ramkoeln', 
'ra-moelder','ramueckeex','ramueller','ranagel','raneumann','ra-neumann','ra-neuwirth','ranoack','ranode','ranode2','ra-notarad-neumann','ransadel','ra-nuss','raoerkuen','raokur','ra-online','ra-pahlisch','ra-paschek', 
'ra-peltzer','raprank','rapsh','ra-rassek.de','rareid','rareinhold-mueller','rariemann','ra-rueffer','rarühen','ra-rupprecht','ra-salemka','ra-sauerbornstorno','ra-schadomsky','raschlor','raschmid',  
'raschmidtke','ra-schorpp','rasehornriedel','ra-shaladi','rastefanbrassel','rastefangoldbeck','ra-stenzel','rastolzenhain','ra-strich','rasw-szb','ra-thiessat','ratomek','ra-trawny','raucurum', 
'ra-uenlue','raunger','ra-unke','ra-vogl','ra-vw','rawaechtler','rawalter','rawasserfall','rawerchan','rawerthern','ra-wh','ra-wirth','rawo','rawo','ra-wolf','ra-ziedrich','ra-zock','rb','r-b-o','rdkk','rebhan','recht3', 
'recht³-sekretariat','recht-bb','recht-beweglich','recht-im-internet','rechtimmittelpunkt','recht-krause','rechtsabteilung dula','rechtsabteilung-gdl','rechtsamt-rostock','recht-sander','rechtsanwaelte-coldewey', 
'rechtsanwaeltepeters','rechtsanwaeltetelgte','rechtsanwaeltin-bohn','rechtsanwaeltin-petereit gespe','rechtsanwaeltin-staehle','rechtsanwalt andreas knopp','rechtsanwalt conradi','rechtsanwalt horn','rechtsanwalt kostka', 
'rechtsanwalt peter schorpp','rechtsanwalt renner','rechtsanwalt schmidt','rechtsanwalt sönke jürgensen','rechtsanwalt thiel','rechtsanwalt ulrich wolf','rechtsanwalt_buddeke','rechtsanwalt-dirk-vollmer', 
'rechtsanwälte hammer & schneid','rechtsanwälte henning cwik','rechtsanwalt-ertunc','rechtsanwalt-fischer','rechtsanwalt-fuessen','rechtsanwalt-hennef','rechtsanwalt-hoefer', 
'rechtsanwalt-kloetzer','rechtsanwaltkrämer','rechtsanwaltlee','rechtsanwalt-lippitz','rechtsanwalt-mager','rechtsanwalt-marionkarpinski','rechtsanwalt-nowak','rechtsanwalt-siemsen','rechtsanwaltskanzlei bierbaum', 
'rechtsanwaltskanzlei gregor ge','rechtsanwaltskanzlei monneck','rechtsanwaltskanzlei paul gesp','rechtsanwaltskanzlei vuletic','rechtsanwaltskanzlei_holzendorf','rechtsanwaltskanzlei-ertogan','rechtsanwaltthran', 
'rechtsanwalt-trub','rechtsanwalt-waldkraiburg','rechtsanwalt-weiss','rechtsanwalt-weissflog','rechtsberatung-karlsruhe','rechtsinformer-deuter','rechtwerk','reeps-malchow','reezmuelroth','reimer-notar','reinhold & linke', 
'reinhold-law','reininghaus','remmert-ra','rennenxyzgesp.','renzelmann','reutherrieche','revo-rechtsanwaelte','revo-rechtsanwälte','rheinen','rheinen1','rhoch','richter','rickart','riemerlaw','riesragmbh','rita-dix','rkb-recht', 
'rkfduygu','rkr','rktest','rktesti','rktesti2','rllemg','robin','roeder-rechtsanwaelte','roemer','roever-ra','rofast','rohrbach','röhrl & braun strafverteidigun','rohs-anwaelte','romberg-juergens','roßbach','rp law','rpmed', 
'rpmed','rpo-online','rptest','rpz','rrt rechtsanwälte','rudolf-koop','rueger','ruehlhick','ruhland-renger','ruhradvokaten','ruisingersteiner','rumpf','ruwcs-gbr','rw-rechtsanwalt','rygodio','rzhup','rzhupawm2','s&r','saager', 
'saalfrank','sabineaalbers','saenger','sahandnourai','sales1','sandrapc','sarx','sassmannshausen','sawko','sbkw','sbr','schaaf','schaarschmidt','schade','schaefer','schaefer','schaefer-bochum','schaeferks','schaeferstr', 
'schafhauser','scharfenberg','scharfenberg und furmaniak ges','scharrer-lemm','schauwienold','scheerermaly','scheidenberger','scheidung-erding','schenk','scherer','schieffer','schieffer_lt_to','schiller-test', 
'schilling-rechtsanwalt','schlatter rechtsanwälte','schlindwein','schlueter','schlung','schmelter & schott','schmidke','schmidtke','schmied','schmittk','schmitz-hoevener','schneider','schneider','schneideranwaelte', 
'schneiderrechtsanwaelte','schneiders','schneider-schreiber-venizeleas','schniebel-steuerrecht','schoelch-schneider','schoenemannmeng','schoeningkanzlei','schrage','schroeder-schulte','schryen','schryen_unna', 
'schuldnerberatung ostberlin ex','schulte & herrmann','schultze','schulung','schulz-henze','schumann','schusue','schwab','schwarz','schwarzenbergerwitt','schwarzhoff','schweitzerundschweitzer','schwemerm', 
'schwerpunktkanzlei-strafrecht','schwirtzek-rechtsanwaelte','scos','screchtsanwaelte','seebacherfleischmannmüller','seeger','seehofer','sefdghy','seidensticker','seitel','semmler','sf','s-factoring','sfcgesp.','sfc-rae', 
'sg-rae','shakespeare','shbp','shelm','shk','shk-dc01','showcase08','showcase10','sh-rae','shw','sicking-herbers','siegburg','siegburgmachensrechtsanwaelte','siepmann','siewer','silberfluss','siller-reuter', 
'simonet pokorny & partner gesp','sittartz-klee-wiechmann','skkanzlei','skr-anwaelte','slomian','smb','smb2019','smerbach','smgtest','sns anwälte','socialsolvent','sodone','soehngenbuellesfeld','soliman','solimantest', 
'sommer.legal','sommer-froehlich','sonja','sophienstr. 3','sören bezzegh','sozialverbandvdk','spiral','sponholz-schoelzel','springmann','sprute rechtsanwälte','sqr-tax','squadcastos','sr','ssf-recht','ssr','ssr-advokat', 
'stadt-kempen-rechtsamt','stadtmoers','staechelin','staehle','stahladmin','stalling','stammen-grote','st-b-k','steeger','steeger','steigerschillundkollegen','stein','steinberg','steinhauer','steinmann','stephanlengnick', 
'stern-schiller','sterrer','steuerw','steuerwerk','steuer-wirtschaftsanwaelte','stiemert','stirnweiss brenner','stirnweiss-brenner','stockburger','stollberg','stompsex','stp.one','stpitc07','stpmuster','str_kanzlei', 
'strafverteidigerkanzlei.legalg','strafverteidigerkanzlei.legalg','strang','strassner-kanzlei','straub - rechtsanwälte notare','streppel','stueberpp','sugrobov','support','supportbo','susannearendt','svenlang','swestendorp', 
'swn','swoboda','swoboda-partner','swp','swpmg','swp-test','sws-rechtsanwaelte','synnott-ra','tabaka','tallen','tandler','tas-legal','tcb','teipel-stirken-kellers','teller','telscher','telscher&kollegen','temmeklein', 
'tes-kanzlei','test','test000','test1','test100','test123@advoware.de','test2','testaccount','testkanzlei','testkanzlei_nichtfreischalten','testkennung','testkk','testkkkorten','testmartin','testml','testtest12','th,sprute', 
'th.sprute','theus rechtsanwälte','theywie','thomas','thomas sturm','thomaskrause','thomsen','thremer & thremer rechtsanwälte','thuemlein','thumm','thumm','tigges und sevis','tinnefeld','tius-kanzlei','tkessel', 
'tobiasholzendorf','tof','töterlöh44','toussi','tp','tragmbh','trameco','trend-makler','trilling','trost','tst-pfoh','tufrei','türkan','tutanota','tws-rechtsanwaelte','tzelepis','ubrasstest','ubtest','ucurum','uhlemann', 
'uhle-ollesch','ulfkoeper','ulrikemangold','ume-mg','unbekannt','unmuessig','unordnungsrecht','ursularehrmann','uyar_rechtsanwaelte','vale-legal','valerie welcker','vankell','vankell','va-ra','vbu-net','vdk_deutschland', 
'vdk_deutschland_1','vechteltest','verkehrsanwalt-rastatt','veronika frey','vgerstorfer','vlf_peters','vmettenheim','vmo','vogelgesang','vogt','voigtmann-legal','vokaten','volker ramge','volkergerstorfer','volkersemler', 
'volker-wulf','vonferber','vonrosenberg','voss-rechtsanwalt','vscr','vuv','wad','wadephul','waetzmann','wagnerhaaserpartmbb (gesperrt)','wagner-schneider','waleczek','wallasch-gaertner','wallasch-gärtner','wanek','warschkow', 
'warthmann','weber und below','weber-ehlen','weberpankl','web-rechtsanwaelte','wecks','wedepohl','weigand-keller','weishaupt-rae','weiss-rechtsanwaeltin','weiss-wunderle','wenzler','werk - gesperrt ms','werner und kollegen', 
'wernerhamm','wernerkonstanz','werwitzkitaubertrechtsanwaelte','wessels','weßlingkambachbitzer','westbunk','westendorf','westendorp','westerfeld','west-kanzlei','wgw','wgw-hagen','whp-kanzlei','wichmann & wortmann','widmann', 
'wieacker','wietbrok','wigge','wigger','wilkerae','winkelmann','winkler','winkler','winter','winter-kanzlei','wirthingenieure','wirz','wissmannlaw','wista-ra','witt-rechtsanwaelte','wkszwickau','wkw-rechtsanwälte', 
'woerner-recht','wohlfahrter','wolf','wolf&partner','wolff göbel wagnergesp','wolfgang jost','wp','wpv-anwaelte','w-rus','wsk rechtsanwälte','wtbrechtanwaelte','wuerzleaicher','wulfra','wullbrandtrae','wüller', 
'wundw1963 - gesperrt','wwsulm','yannicktest','yesorak','yuropl','za4611p18071314','zachhuber & schönrock','zanisc','zdarsky','zecher','zeeb','zeller','ziebach','ziehm und partner','zimmer','zinser', 
'zischka-recht','zkhrechtsanwaelte','zk-strafrecht','zosierei','zweibackes');

    private $_connectorKanzleienAlleOrdner = array('mltestXXX'); // Hier kann für eine Kanzlei aktiviert werden, dass alle Ordner anezeigt werden also auch die anderen Mitarbeiter
    private $_access_token;
    private $_tokenData;
    private $_forceConnector = array('steinbock-partner', 'ra-holler', 'koenigswall'); // kein Fallback auf Freigabediennst

	public function GetSupportedASVersion() {
		return ZPush::ASV_14;
	}

    private function GetUrlsFromAdvonetConfigurator() {
        // Holt die aktuelle Url Konfiguration der Kanzlei. Im Fehlerfall werden die Standardwerte aus der Konfiguration übernommen (config.php)
        if (!isset($this->_baseUrlTermine) || !isset($this->_baseUrlTodos) || !isset($this->_securityGatewayUrl) || !isset($this->_relayUrl)) {
            $relayUrl = getenv('ZPUSH_ENV_SPK2_DEFAULT_RELAY_URL');
            $securityGatewayUrl = getenv('ZPUSH_ENV_SPK2_DEFAULT_SECURITY_GATEWAY_URL');
            $urlConfigurator = getenv('ZPUSH_ENV_SPK2_ADVONET_URL_CONFIGURATOR_URL') . urlencode($this->_kanzlei);
		try {
					$time_start = microtime(true);
					$rest = \Httpful\Request::get($urlConfigurator)
						->expectsJson()
						->timeout(15)
						->send();
                ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Check for Urls from AdvoNetConfigurator Executiontime: %f seconds", microtime(true) - $time_start));
					if (!$rest->hasErrors()) {
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Response from AdvoNetConfigurator: %s", print_r($rest->body, true)));
                    if (isset($rest->body->relayUrl) && !empty($rest->body->relayUrl)) {
                        $relayUrl = $rest->body->relayUrl;
					}
                    if (isset($rest->body->securityGateway) && !empty($rest->body->securityGateway)) {
                        $securityGatewayUrl = $rest->body->securityGateway;
					}
                } else {
                    if ($rest->hasBody()) {
                        ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Error checking for Urls from AdvoNetConfigurator error code: %s, text: %s", $rest->code, print_r($rest->body, true)));
                    } else {
                        ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Error checking for Urls from AdvoNetConfigurator: HTTP Status " . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
                    }
                }
            } catch (Exception $ex) {
                ZLog::Write(LOGLEVEL_WARN, sprintf("BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Check for Urls from AdvoNetConfigurator exception: %s", $ex->getMessage()));
            }
            $this->_relayUrl = $relayUrl;
            $this->_baseUrlTermine = $relayUrl . RELAY_REST_URL_TERMINE_SUFFIX;
            $this->_baseUrlTodos = $relayUrl . RELAY_REST_URL_TODOS_SUFFIX;
            $this->_securityGatewayUrl = $securityGatewayUrl;
        }
        ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetUrlsFromAdvonetConfigurator() Using Relay=%s SecurityGateway=%s", $this->_relayUrl, $this->_securityGatewayUrl));
    }

    private function CacheConfig($ttl) {
        // Die ausgelesene Konfigruation + token in den Cache schreiben
        try {
            $redis = new Redis();
            $redis->connect(getenv('ZPUSH_ENV_SPK2_REDIS_SERVER'));
            $hash = hash('sha256', $this->_usernameRest . "_" . $this->_passwortRest . "_" . $this->_kanzlei . "_" . $this->_datenbank);
            $redisKeyTokenData = $hash . "_tokenData";
            $redisKeyFolder = $hash . "_folder";
            $redisKeyRelayUrl = $hash . "_relayUrl";
            $redisKeySecurityGatewayUrl = $hash . "_securityGatewayUrl";
            $redis->setex($redisKeyTokenData, $ttl, $this->_tokenData);
            $redis->setex($redisKeyFolder, $ttl, json_encode($this->_mitarbeiter));
            $redis->setex($redisKeyRelayUrl, $ttl, $this->_relayUrl);
            $redis->setex($redisKeySecurityGatewayUrl, $ttl, $this->_securityGatewayUrl);
        } catch (Exception $ex) {
            ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->CacheConfig() Exception: " . $ex->getMessage());
        }
    }

    private function GetCachedConfig() {
        // Prüfen ob in Redis bereits ein gültiges Token und sonstige Konfigurationsdaten gespeichert sind
        // Achtung: Durch das cachen der RelayUrl / SecurityGatewayUrl kann es zu Problemen nach deren Änderung kommen. Passiert nur beim Testen, nicht produktiv...
        try {
            $redis = new Redis();
            $redis->connect(getenv('ZPUSH_ENV_SPK2_REDIS_SERVER'));
            $hash = hash('sha256', $this->_usernameRest . "_" . $this->_passwortRest . "_" . $this->_kanzlei . "_" . $this->_datenbank);
            $redisKeyTokenData = $hash . "_tokenData";
            $redisKeyFolder = $hash . "_folder";
            $redisKeyRelayUrl = $hash . "_relayUrl";
            $redisKeySecurityGatewayUrl = $hash . "_securityGatewayUrl";
            $this->_tokenData = $redis->get($redisKeyTokenData);
            $folder = json_decode($redis->get($redisKeyFolder));
            $relayUrl = $redis->get($redisKeyRelayUrl);
            $securityGatewayUrl = $redis->get($redisKeySecurityGatewayUrl);
            if (!empty($this->_tokenData) && !empty($folder) && !empty($relayUrl) && !empty($securityGatewayUrl)) {
                $cachedTokenData = json_decode($this->_tokenData);
                if (isset($cachedTokenData->expiration_utc) && (strlen($cachedTokenData->expiration_utc) > 0) && (strlen($cachedTokenData->access_token) > 0)) {
                    $tokenExpirationUtc = date_create_from_format('Y-m-d\TH:i:s\Z', substr($cachedTokenData->expiration_utc, 0, strpos($cachedTokenData->expiration_utc, '.')) . 'Z', timezone_open("UTC"));
                    $objDateTimeNow = new DateTime('NOW');
                    $objDateTimeNow->setTimezone(new DateTimeZone("UTC"));
                    $diffInMinutes = intdiv($tokenExpirationUtc->getTimestamp() - $objDateTimeNow->getTimestamp(), 60);
                    $this->_mitarbeiter = $folder;
                    $this->_relayUrl = $relayUrl;
                    $this->_baseUrlTermine = $relayUrl . RELAY_REST_URL_TERMINE_SUFFIX;
                    $this->_baseUrlTodos = $relayUrl . RELAY_REST_URL_TODOS_SUFFIX;
                    $this->_securityGatewayUrl = $securityGatewayUrl;
                    $this->_access_token = $cachedTokenData->access_token;
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetCachedConfig() Found cached config valid for %d minutes.", $diffInMinutes));
                    return true;
                }
            }
        } catch (Exception $ex) {
            ZLog::Write(LOGLEVEL_WARN, "BackendOnlineAkte->GetCachedConfig() Acquiring config from cache failed with exception: " . $ex->getMessage());
        }
        $this->_tokenData = "";
        return false;
    }

    private function GetToken() {
        // Neues Token vom Security Gateway anfordern
        // Return: Gültigkeit des Token in Sekunden oder 0 bei Fehler

        $exceptionMsg = "Error getting token: Unknown Error in GetToken";

        try {
            $identityHMAC = new IdentityHMAC();
            $identityHMAC->AppID = "SPK_2.0";
            $identityHMAC->Kanzlei = htmlspecialchars_decode($this->_kanzlei);
            $identityHMAC->Database = $this->_datenbank;
            $identityHMAC->User = htmlspecialchars_decode($this->_kuerzel);
            $identityHMAC->Password = $this->_passwortRest;
            $identityHMAC->Role = 2;
            $identityHMAC->Product = 1;

            $body = $identityHMAC->Sign(getenv('ZPUSH_ENV_SPK2_APIKEY'));

            try {
                $rest = \Httpful\Request::post($this->_securityGatewayUrl . SECURITY_GATEWAY_TOKEN_URL_SUFFIX)
                        ->body($body)
                        ->expectsJson()
                        ->timeout(15)
                        ->sendsJson()
                        ->send();
                if (!$rest->hasErrors()) {
                    if ($rest->hasBody()) {
                        //$tokenData = json_decode($rest->body);
                        $tokenData = $rest->body;
                        ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetToken() tokenData=%s", print_r($tokenData, true)));
                        if (isset($tokenData->expiration_utc) && (strlen($tokenData->expiration_utc) > 0) && (strlen($tokenData->access_token) > 0)) {
                            $tokenExpirationUtc = date_create_from_format('Y-m-d\TH:i:s\Z', substr($tokenData->expiration_utc, 0, strpos($tokenData->expiration_utc, '.')) . 'Z', timezone_open("UTC"));
                            //$tokenExpirationUtc = date_create_from_format("", $tokenData->expiration_utc, new DateTimeZone("UTC"));
                            $objDateTimeNow = new DateTime('NOW');
                            $objDateTimeNow->setTimezone(new DateTimeZone("UTC"));
                            $diffInSeconds = $tokenExpirationUtc->getTimestamp() - $objDateTimeNow->getTimestamp();

                            if ($diffInSeconds >= intval(getenv('ZPUSH_ENV_SPK2_MIN_TOKEN_VALIDITY_SECONDS'))) {
                                $ttl = $diffInSeconds - intval(getenv('ZPUSH_ENV_SPK2_MIN_TOKEN_VALIDITY_SECONDS')); // x Sekunden vor Ablauf des token diesen aus dem Cache löschen
                                $this->_tokenData = json_encode($tokenData);
                                $this->_access_token = $tokenData->access_token;
                                ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->GetToken() Got new token with ttl = %d minutes", intdiv($diffInSeconds, 60)));
                                return $ttl;
                            } else {
                                $exceptionMsg = sprintf("Error getting token: Got expired token with ttl = %d seconds", $diffInSeconds);
                                ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
                            }
                        } else {
                            $exceptionMsg = "Error getting token: Got invalid token, no expiration_utc or access_token!";
                            ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
                        }
                    } else {
                        $exceptionMsg = "Error getting token: Got response without body!";
                        ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
                    }
                } else {
                    if ($rest->hasBody()) {
                        $exceptionMsg = sprintf("Error getting token: HTTP status: %s, Body: %s", $rest->code, print_r($rest->body, true));
                        ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
                    } else {
                        $exceptionMsg = "Error getting token: HTTP Status: " . $rest->code . " Body: " . $rest->raw_body;
                        ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() Error getting token: HTTP Status " . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
                    }
                }
            } catch (Exception $fault) {
                $exceptionMsg = "Error getting token: Acquiring new token failed with exception: " . $fault->getMessage();
                ZLog::Write(LOGLEVEL_WARN, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
            }

        } catch (Exception $ex) {
            $exceptionMsg = "Error getting token: Failed with exception: " . $ex->getMessage();
            ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetToken() " . $exceptionMsg);
        }
        throw new Exception($exceptionMsg);
    }

    private function GetMitarbeiter() {
        // - Mitarbeiter  (=folder) auslesen
        // Kunden wollen die Ordner Ihrer Kollegen bisher nicht sehen

        if (!in_array(strtolower($this->_kanzlei), $this->_connectorKanzleienAlleOrdner)) {
            $this->_mitarbeiter[] = $this->_kuerzel;
        } else {
            // ToDo: Wenn die Ordnerunterstützung nach der Abschaltung von Basic Auth wieder gehen soll muss hier und
            // im SecurityGateway angepasst und das token verwendet werden 
            $url = $this->_relayUrl . "api/v1/license/KuerzelForProduct/1"; // 1 = Smartphone-Kalender
					$time_start = microtime(true);
					$rest = \Httpful\Request::get($url)
						->expectsJson()
                    ->authenticateWith($this->_usernameRest, $this->_passwortRest)
						->timeout(15)
						->send();
            ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetMitarbeiter() Check for connector Executiontime: %f seconds", microtime(true) - $time_start));
					if (!$rest->hasErrors()) {
                ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetMitarbeiter() Response KuerzelForProduct/1: %s", print_r($rest->body, true)));
						foreach($rest->body as $folder) {
									$this->_mitarbeiter[] = $folder;
							}
            } else {
                if ($rest->hasBody()) {
                    ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->GetMitarbeiter() Error code: %s, text: %s", $rest->code, print_r($rest->body, true)));
                } else {
                    ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetMitarbeiter() Error: HTTP Status " . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
							}
						}
					}
					}

    /**
     * Login to the OnlineAkte backend
     * @see IBackend::Logon()
     */
    private function LogonRest($username, $domain, $password) {
        try {
            $this->_usernameRest = mb_convert_encoding($username, "ISO-8859-1", "UTF-8");
            $this->_passwortRest = mb_convert_encoding($password, "ISO-8859-1", "UTF-8");
            $this->_frnrSuffix = preg_replace("/[^A-Za-z0-9@#]/", "_", $username);

            // prüfen ob Kanzlei bereits connector verwendet
            // if (in_array(mb_strtolower($this->_kanzlei), $this->_connectorKanzleien)) {
            if (!in_array(mb_strtolower($this->_kanzlei), $this->_spk10Kanzleien)) {
                if ($this->GetCachedConfig()) {
                    return true;
				}
                $this->GetUrlsFromAdvonetConfigurator();
                $this->GetMitarbeiter();
                $ttl = $this->GetToken();
                if ($ttl > 0) {
                    $this->CacheConfig($ttl);
                    return true;
				}
			}

			if (!$this->UseConnector()) {
				// kein connector
				$this->_baseUrlTermine = ONLINEAKTE_REST_URL_TERMINE;
				$this->_baseUrlTodos = ONLINEAKTE_REST_URL_TODO;
				$url = $this->_baseUrlTermine . "/0";
				$time_start = microtime(true);
				$response = \Httpful\Request::get($url)
                        ->authenticateWith($this->_usernameRest, $this->_passwortRest)
					->withoutAutoParsing()
					->send();
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->LogonRest() Executiontime: %f seconds", microtime(true) - $time_start));
				if ($response->hasErrors()) {
                    if ($response->hasBody()) {
						throw new Exception(print_r($response->body, true));
                    } else {
                        throw new Exception('Fehler in LogonRest ohne Connector bei GET ' . $url . ': HTTP Status ' . $response->code . "\r\n\r\nHeaders: " . $response->raw_headers . "\r\n\r\nBody: " . $response->raw_body . "\r\n\r\n");
				}
			}
            } else {
				// connector wird verwendet
                ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->LogonRest Found connector with %s folders and relayurl %s", count($this->_mitarbeiter), $this->_baseUrlTermine));
			}
			return true;
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->LogonRest() Username: %s Exception: %s", $this->_usernameRest, $fault->getMessage()));
            if (strpos($fault->getMessage(), FEHLER_FALSCHES_KENNWORT) !== false) {
				// Nichts machen, am Ende wird false zurück gegeben was zu einer AuthenticationRequiredException führt
            } elseif (strpos($fault->getMessage(), FEHLER_FALSCHES_KENNWORT_2) !== false) {
                // Nichts machen, am Ende wird false zurück gegeben was zu einer AuthenticationRequiredException führt
            } else {
                $this->ThrowAdvoNetException($fault);
			}
			}
			return false;
		}

	public function Logon($username, $domain, $password) {
        ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->Logon() User: '%s'", $username));
		// Der Benutzername wird wie folgt erwartet: Kuerzel@@Kanzlei@@Datenbank (z.B ML@@matthias-test-kanzlei@@MATTHIAS)
		$strTrennzeichen = '@@';
		$iPos = strpos($username, $strTrennzeichen);
		if ($iPos === false) {
			$strTrennzeichen = '##';
			$iPos = strpos($username, $strTrennzeichen);
		}
		if ($iPos > 0) {
            ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->Logon() Found: '%s'", $strTrennzeichen));
			$this->_kuerzel = htmlspecialchars(substr($username, 0, $iPos));
			mb_detect_encoding($this->_kuerzel, 'UTF-8', true) == "UTF-8" ? : $this->_kuerzel = utf8_encode($this->_kuerzel);
			$iPos2 = strpos($username, $strTrennzeichen, $iPos + 1);
            ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->Logon() Kuerzel: '%s'", $this->_kuerzel));
			if ($iPos2 > $iPos) {
				$this->_kanzlei = htmlspecialchars(substr($username, $iPos + strlen($strTrennzeichen), $iPos2 - $iPos - strlen($strTrennzeichen)));
				mb_detect_encoding($this->_kanzlei, 'UTF-8', true) == "UTF-8" ? : $this->_kanzlei = utf8_encode($this->_kanzlei);
				$this->_datenbank = htmlspecialchars(substr($username, $iPos2 + strlen($strTrennzeichen)));
				mb_detect_encoding($this->_datenbank, 'UTF-8', true) == "UTF-8" ? : $this->_datenbank = utf8_encode($this->_datenbank);
				if (strlen($this->_datenbank) > 0) {
					$this->_passwort = htmlspecialchars($password);
					mb_detect_encoding($this->_passwort, 'UTF-8', true) == "UTF-8" ? : $this->_passwort = utf8_encode($this->_passwort);
					if ($this->_kanzlei == 'mltest') {
                        ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->Logon() password: [%s] encoded [%s]", $password, $this->_passwort));
					}
					return $this->LogonRest($username, $domain, $password);
				}
			}
            ZLog::Write(LOGLEVEL_WARN, sprintf("BackendOnlineAkte->Logon() LogonKalenderSB failed! Kuerzel: %s Kanzlei: %s Datenbank: %s", $this->_kuerzel, $this->_kanzlei, $this->_datenbank));
			return false;
		}
	}

        /**
	 * The connections to OnlineAkte are always directly closed. So nothing special needs to happen here.
	 * @see IBackend::Logoff()
	 */
    public function Logoff() {
		return true;
	}

	/**
	 * OnlineAkte doesn't need to handle SendMail
	 * @see IBackend::SendMail()
	 */
    public function SendMail($sm) {
		return false;
	}

	/**
	 * No attachments in OnlineAkte
	 * @see IBackend::GetAttachmentData()
	 */
    public function GetAttachmentData($attname) {
		return false;
	}

	/**
	 * Deletes are always permanent deletes. Messages doesn't get moved.
	 * @see IBackend::GetWasteBasket()
	 */
    public function GetWasteBasket() {
		return false;
	}

	/**
	 * Get a list of all the folders we are going to sync.
	 * @see BackendDiff::GetFolderList()
	 */
	public function GetFolderList() {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte::GetFolderList()"));
		$folders = array();
		$folder = $this->StatFolder("calendar");
		$folders[] = $folder;
		$folder = $this->StatFolder("tasks");
		$folders[] = $folder;
		$folder = $this->StatFolder("deleteditems");
		$folders[] = $folder;
		$folder = $this->StatFolder("Inbox");
		$folders[] = $folder;
		$folder = $this->StatFolder("Drafts");
		$folders[] = $folder;
		$folder = $this->StatFolder("Sent");
		$folders[] = $folder;
		$folder = $this->StatFolder("Outbox");
		$folders[] = $folder;
		$folder = $this->StatFolder("Trash");
		$folders[] = $folder;

		foreach ($this->_mitarbeiter as $name) {
			$folder = $this->StatFolder("calendar_" . $name);
			$folders[] = $folder;
			$folder = $this->StatFolder("tasks_" . $name);
			$folders[] = $folder;
		}

		return $folders;
	}

	/**
	 * Returning a SyncFolder
	 * @see BackendDiff::GetFolder()
	 */
	public function GetFolder($id) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetFolder('%s')", $id));
		if ($id == "calendar") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			if ($this->UseConnector()) {
				$folder->displayname = "Allgemein " . $this->_datenbank;
            } else {
				$folder->displayname = $this->_kuerzel . " " . $this->_datenbank;
			}
			$folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
			return $folder;
        } elseif ($id == "tasks") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			if ($this->UseConnector()) {
				$folder->displayname = "Pool " . $this->_datenbank;
            } else {
				$folder->displayname = $this->_kuerzel . " " . $this->_datenbank;
			}
			$folder->type = SYNC_FOLDER_TYPE_TASK;
			return $folder;
        } elseif ($id == "deleteditems") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Papierkorb";
			$folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
			return $folder;
        } elseif ($id == "Inbox") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Inbox";
			$folder->type = SYNC_FOLDER_TYPE_INBOX;
			return $folder;
        } elseif ($id == "Drafts") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Drafts";
			$folder->type = SYNC_FOLDER_TYPE_DRAFTS;
			return $folder;
        } elseif ($id == "Sent") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Sent";
			$folder->type = SYNC_FOLDER_TYPE_SENTMAIL;
			return $folder;
        } elseif ($id == "Outbox") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Outbox";
			$folder->type = SYNC_FOLDER_TYPE_OUTBOX;
			return $folder;
        } elseif ($id == "Trash") {
			$folder = new SyncFolder();
			$folder->serverid = $id;
			$folder->parentid = "0";
			$folder->displayname = "Trash";
			$folder->type = SYNC_FOLDER_TYPE_WASTEBASKET;
			return $folder;
        } else {
			$kuerzel = $this->getKuerzelFromFolderId($id);
			if (!empty($kuerzel)) {
				if (in_array($kuerzel, $this->_mitarbeiter)) {
					$folder = new SyncFolder();
					$folder->serverid = $id;
					$folder->parentid = "0";
					$folder->displayname = $kuerzel . " " . $this->_datenbank;
					if (substr($id, 0, strlen("calendar_")) === "calendar_") {
						$folder->type = SYNC_FOLDER_TYPE_APPOINTMENT;
						return $folder;
                    } elseif (substr($id, 0, strlen("tasks_")) === "tasks_") {
						$folder->type = SYNC_FOLDER_TYPE_TASK;
						return $folder;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Returns information on the folder.
	 * @see BackendDiff::StatFolder()
	 */
	public function StatFolder($id) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->StatFolder('%s')", $id));
		$folder = $this->GetFolder($id);
		$stat = array();
		$stat["id"] = $id;
		$stat["parent"] = $folder->parentid;
		$stat["mod"] = $folder->displayname;
		return $stat;
	}

	/**
	 * ChangeFolder is not supported under OnlineAkte
	 * @see BackendDiff::ChangeFolder()
	 */
    public function ChangeFolder($folderid, $oldid, $displayname, $type) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeFolder('%s','%s','%s','%s')", $folderid, $oldid, $displayname, $type));
		return false;
	}

	/**
	 * DeleteFolder is not supported under OnlineAkte
	 * @see BackendDiff::DeleteFolder()
	 */
    public function DeleteFolder($id, $parentid) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->DeleteFolder('%s','%s')", $id, $parentid));
		return false;
	}

    private function UseConnector() {
		// if (in_array(mb_strtolower($this->_kanzlei), $this->_forceConnector)) {
		// MTepel: Fallback to SPK 1.0 deactivated on 11.07.2024

                // MTepel: SPK 2.0 as default for new customers (03.03.2026)
                // if (in_array(mb_strtolower($this->_kanzlei), $this->_connectorKanzleien)) {
                if (!in_array(mb_strtolower($this->_kanzlei), $this->_spk10Kanzleien)) {
                    if (empty($this->_mitarbeiter)) {
		        ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->UseConnector Forcing connector usage for '%s'", $this->_kanzlei));
                        $this->_mitarbeiter[] = $this->_kuerzel;
                    }
                    return true;
                }
                else {
                    return !empty($this->_mitarbeiter);
                }
	}

	private function getKuerzelFromFolderId($folderid) {
		if (substr($folderid, 0, strlen("calendar_")) === "calendar_") {
			return substr($folderid, strlen("calendar_"));
        } elseif (substr($folderid, 0, strlen("tasks_")) === "tasks_") {
			return substr($folderid, strlen("tasks_"));
        } else {
            return "";
		}
	}

	function getLineBreakType($strTemp) {
        if (strlen($strTemp) == 0) {
			return false;
        }
		$strEOL = '';
		$iPosEOL = strpos($strTemp, "\r\n");
        if ($iPosEOL === false) {
			$iPosEOL = strpos($strTemp, "\n");
            if ($iPosEOL === false) {
				$iPosEOL = strpos($strTemp, "\r");
                if ($iPosEOL === false) {
                    ZLog::Write(LOGLEVEL_WARN, sprintf("BackendOnlineAkte->getLineBreakType() Could not determine type of linebreak"));
					return false;
                } else {
					$strEOL = "\r";
                    ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->getLineBreakType() \r inebreaks detected');
				}
            } else {
				$strEOL = "\n";
                ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->getLineBreakType() \n linebreaks detected');
			}
        } else {
			$strEOL = "\r\n";
            ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->getLineBreakType() \r\n linebreaks detected');
		}
        if (strlen($strEOL) > 0) {
			return $strEOL;
        } else {
			return false;
		}
	}

	function GetToDosFromOnlineAkteRest($folderid, $start = null, $finish = null) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetToDosFromOnlineAkteRest('%s','%s','%s')", $folderid, $start, $finish));
		$report = array();
		try {
			$url = ONLINEAKTE_REST_URL_TODO . "?from=" . $start . "&to=" . $finish;
            $time_start = microtime(true);
			if ($this->UseConnector()) {
				$kuerzel = $this->getKuerzelFromFolderId($folderid);
                if (empty($kuerzel)) {
					$kuerzel = "Pool";
                }
				$url = $this->_baseUrlTodos . "?from=" . $start . "&to=" . $finish . "&kuerzel=" . urlencode($kuerzel);
			$rest = \Httpful\Request::get($url)
				->expectsJson()
                        ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                        ->send();
            } else {
                $rest = \Httpful\Request::get($url)
                        ->expectsJson()
				->authenticateWith($this->_usernameRest, $this->_passwortRest)
				->send();
            }
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetToDosFromOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
			if ($rest->hasErrors()) {
                if ($rest->hasBody()) {
					throw new Exception(print_r($rest->body, true));
                } else {
                    throw new Exception('Fehler in GetToDosFromOnlineAkteRest bei GET ' . $url . ': HTTP Status ' . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
			}
            }
			foreach($rest->body as $todo) {
				//echo var_dump($termin);
				$response = array();
				$response['data'] = $todo;
				$response['href'] = $todo->FrNr . "@TODO." . $this->_frnrSuffix;
				$report[] = $response;
			}
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, 'BackendOnlineAkte->GetToDosFromOnlineAkteRest() Exception: ' . $fault->getMessage());
            $this->ThrowAdvoNetException($fault);
		}
        return $report;
    }

    function ThrowAdvoNetException($fault) {
			// Exception werfen entsprechend der Situation:
			if (strpos($fault->getMessage(), FEHLER_NICHT_FREIGESCHALTET)) {
				throw new OnlineakteException("Onlineakte Freischaltfehler", 503);
        } elseif (strpos($fault->getMessage(), FEHLER_NICHT_ANGEMELDET)) {
				throw new OnlineakteException("Onlineakte Datenfreigabedienstfehler", 503);
        } elseif (strpos($fault->getMessage(), FEHLER_NICHT_REGISTRIERT)) {
            throw new OnlineakteException("AdvoNet Connector Fehler", 503);
        } elseif (strpos($fault->getMessage(), FEHLER_FALSCHES_KENNWORT)) {
				throw new OnlineakteException("Onlineakte Anmeldefehler", 503);
        } elseif (strpos($fault->getMessage(), FEHLER_FALSCHES_KENNWORT_2)) {
            throw new OnlineakteException("AdvoNet Anmeldefehler", 503);
        } else {
            if ($this->UseConnector()) {
                throw new OnlineakteException("AdvoNet Netzwerkfehler", 503);
            } else {
				throw new OnlineakteException("Onlineakte Netzwerkfehler", 503);
			}
		}
	}

	function GetEventsFromOnlineAkteRest($folderid, $start = null, $finish = null) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetEventsFromOnlineAkteRest('%s', %s','%s')", $folderid, $start, $finish));
		$report = array();
		try {
			$url = ONLINEAKTE_REST_URL_TERMINE . "?from=" . $start . "&to=" . $finish;
            $time_start = microtime(true);
			if ($this->UseConnector()) {
				$kuerzel = $this->getKuerzelFromFolderId($folderid);
                if (empty($kuerzel)) {
					$kuerzel = "Allgemein";
                }
				$url = $this->_baseUrlTermine . "?from=" . $start . "&to=" . $finish . "&kuerzel=" . urlencode($kuerzel);
			$rest = \Httpful\Request::get($url)
				->expectsJson()
                        ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                        ->send();
            } else {
                $rest = \Httpful\Request::get($url)
                        ->expectsJson()
				->authenticateWith($this->_usernameRest, $this->_passwortRest)
				->send();
            }
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetEventsFromOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
			if ($rest->hasErrors()) {
                if ($rest->hasBody()) {
					throw new Exception(print_r($rest->body, true));
                } else {
                    throw new Exception('Fehler in GetEventsFromOnlineAkteRest: HTTP Status ' . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
			}
            }

			foreach($rest->body as $termin) {
				//echo var_dump($termin);
				$response = array();
				$response['data'] = $termin;
				$response['href'] = $termin->FrNr . "@" . $this->_frnrSuffix;
				$report[] = $response;
			}
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, 'BackendOnlineAkte->GetEventsFromOnlineAkteRest() Exception: ' . $fault->getMessage());
            $this->ThrowAdvoNetException($fault);
		}
		return $report;
	}

	/**
	 * Get a list of all the messages.
	 * @see BackendDiff::GetMessageList()
	 */
    public function GetMessageList($folderid, $cutoffdate) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetMessageList('%s','%s')", $folderid, $cutoffdate));

		/* Calculating the range of events we want to sync */
		//$begin = gmdate("Ymd\THis\Z", $cutoffdate);
		//$finish = gmdate("Ymd\THis\Z", 2147483647);
		$begin = date("Y-m-d\TH:i:sP", $cutoffdate);
		$finish = date("Y-m-d\TH:i:sP", 2147483647);
		$beginRest = date("Y-m-d", $cutoffdate);
		$finishRest = date("Y-m-d", 2147483647);

        if (substr($folderid, 0, strlen("calendar")) === "calendar") {
			$msgs = $this->GetEventsFromOnlineAkteRest($folderid, $beginRest, $finishRest);
        } else if (substr($folderid, 0, strlen("tasks")) === "tasks") {
			$msgs = $this->GetToDosFromOnlineAkteRest($folderid, $beginRest, $finishRest);
        } else {
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetMessageList('%s','%s') Unsupported folder!", $folderid, $cutoffdate));
			$msgs = array();
		}

		$messages = array();
		//ZLog::Write(LOGLEVEL_WARN, 'BackendOnlineAkte->getMessageList(): msgs imploded: ' . implode($msgs));
        foreach ($msgs as $e) {
			$id = $e['href'];
			$this->_collection[$id] = $e;
			$messages[] = $this->StatMessage($folderid, $id);
		}
        ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->GetMessageList REST('%s',cutoffdate='%s') Got %s elements", $folderid, $begin, count($messages)));
		return $messages;
	}

	/**
	 * Get a SyncObject by its ID
	 * @see BackendDiff::GetMessage()
	 */
    public function GetMessage($folderid, $id, $contentparameters) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetMessage('%s','%s')", $folderid,  $id));
		$data = $this->_collection[$id]['data'];

        if (substr($folderid, 0, strlen("calendar")) === "calendar") {
			return $this->_ParseVEventToASRest($data, $contentparameters);
        } elseif (substr($folderid, 0, strlen("tasks")) === "tasks") {
			return $this->_ParseVTodoToASRest($data, $contentparameters); 
		}
		return false;
	}

	function GetEntryByUidFromOnlineAkteRest( $folderid, $id ) {
		$frnr = substr($id, 0, strpos($id,'@'));
		$report = array();
		try {
			if (substr($folderid, 0, strlen("tasks")) === "tasks") {
				$url = ONLINEAKTE_REST_URL_TODO . "/" . $frnr;
                $time_start = microtime(true);
				if ($this->UseConnector()) {
					$url = $this->_baseUrlTodos . "?frnr=" . $frnr;
				$rest = \Httpful\Request::get($url)
					->expectsJson()
                            ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                            ->send();
                } else {
                    $rest = \Httpful\Request::get($url)
                            ->expectsJson()
					->authenticateWith($this->_usernameRest, $this->_passwortRest)
					->send();
                }
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
				if ($rest->hasErrors()) {
                    if ($rest->hasBody()) {
						throw new Exception(print_r($rest->body, true));
                    } else {
                        throw new Exception('Fehler in GetEntryByUidFromOnlineAkte: HTTP Status ' . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
				}
                }
				$response = array();
				$response['data'] = $rest->body;
				$response['href'] = $rest->body->FrNr . "@TODO." . $this->_frnrSuffix;
				$report[] = $response;
            } else if (substr($folderid, 0, strlen("calendar")) === "calendar") {
				$url = ONLINEAKTE_REST_URL_TERMINE . "/" . $frnr;
                $time_start = microtime(true);
				if ($this->UseConnector()) {
					$url = $this->_baseUrlTermine . "?frnr=" . $frnr;
				$rest = \Httpful\Request::get($url)
					->expectsJson()
                            ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                            ->send();
                } else {
                    $rest = \Httpful\Request::get($url)
                            ->expectsJson()
					->authenticateWith($this->_usernameRest, $this->_passwortRest)
					->send();
                }
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
				if ($rest->hasErrors()) {
                    if ($rest->hasBody()) {
						throw new Exception(print_r($rest->body, true));
                    } else {
                        throw new Exception('Fehler in GetEntryByUidFromOnlineAkte: HTTP Status ' . $rest->code . "\r\n\r\nHeaders: " . $rest->raw_headers . "\r\n\r\nBody: " . $rest->raw_body . "\r\n\r\n");
				}
                }
				$response = array();
				$response['data'] = $rest->body;
				$response['href'] = $rest->body->FrNr . "@" . $this->_frnrSuffix;
				$report[] = $response;
            } else {
                ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() unknown folderid: " . $folderid);
			}
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() Fehler Start folderid=%s id=%s", $folderid, $id));
            ZLog::Write(LOGLEVEL_ERROR, 'BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() ' . $fault->getMessage());
            ZLog::Write(LOGLEVEL_ERROR, 'BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() ' . print_r($fault, true));
            ZLog::Write(LOGLEVEL_ERROR, 'BackendOnlineAkte->GetEntryByUidFromOnlineAkteRest() Fehler Stop');
            $this->ThrowAdvoNetException($fault);
			}

		return $report;
	}

	/**
	 * Return id, flags and mod of a messageid
	 * @see BackendDiff::StatMessage()
	 */
    public function StatMessage($folderid, $id) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->StatMessage('%s','%s')", $folderid,  $id));
		//ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->StatMessage(): collection: ' . print_r($this->_collection, true));
		$type = "VEVENT";
        if (substr($folderid, 0, strlen("tasks")) === "tasks") {
			$type = "VTODO";
		}
		$data = null;
        if (array_key_exists($id, $this->_collection)) {
			$data = $this->_collection[$id];
			//ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->StatMessage(): cache HIT');
        } else {
			if (strpos($id, ".VORTERMIN") > 0 || strpos($id, ".HINFAHRT") > 0 || strpos($id, ".RUECKFAHRT") > 0 || strpos($id, ".URLAUB") > 0)
				return;
			if (strpos($id, "-") === 0)
				return; // Beim neuen REST Service sind die Spezialtermine mit frnr < 0
			if (strpos($id,'@') <= 0) // Ignorierte Termine die in AW ungueltig waeren
				return;
			// $e = $this->GetEntryByUidFromOnlineAkte(substr($id, 0, strpos($id,'@')));
			$e = $this->GetEntryByUidFromOnlineAkteRest($folderid, $id);
			if ($e == null && count($e) <= 0)
				return;
			$data = $e[0];
		}
		$message = array();
		$message['id'] = $data['href'];
		$message['flags'] = "1";
		// Folgender Aufruf muesste fuer Termine und Aufgaben gleich sein:
		if (strpos($data['data']->ZuletztGeaendertAm, '.')) {
			$newDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', substr($data['data']->ZuletztGeaendertAm, 0, strpos($data['data']->ZuletztGeaendertAm, '.')) . 'Z', timezone_open("UTC")); //2016-06-22T13:17:01.608 ältere php können nur 6stellige microseconds, also abschneiden
//                    if (!$newDateTime) {
//                        ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): date_get_last_errors: ' . print_r(date_get_last_errors(), true), false);
//                    }
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): ZuletztGeaendertAm: ' . print_r($data['data']->ZuletztGeaendertAm, true), false);
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): data: ' . print_r($data['data'], true), false);
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): newDateTime: ' . print_r($newDateTime, true), false);
				//$message['mod'] = $newDateTime->getTimestamp();
			$message['mod'] = date_format($newDateTime, "Ymd\THis\Z");
        } else {
			$newDateTime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $data['data']->ZuletztGeaendertAm, timezone_open("UTC")); //2016-06-22T13:17:01
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): ZuletztGeaendertAm: ' . print_r($data['data']->ZuletztGeaendertAm, true), false);
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): data: ' . print_r($data['data'], true), false);
//                    ZLog::Write(LOGLEVEL_INFO, 'BackendOnlineAkte->StatMessage(): newDateTime: ' . print_r($newDateTime, true), false);
//                    $message['mod'] = $newDateTime->getTimestamp();
			$message['mod'] = date_format($newDateTime, "Ymd\THis\Z");
		}
		return $message;
	}

	function ChangeMessageOnlineAkteRest( $data, $folderid, $isNew = true) {
		try {
			if ($isNew) {
				$url = ONLINEAKTE_REST_URL_TERMINE;
				if ($this->UseConnector()) {
					$url = $this->_baseUrlTermine;
				}
				if (substr($folderid, 0, strlen("tasks")) === "tasks") {
					$url = ONLINEAKTE_REST_URL_TODO;
					if ($this->UseConnector()) {
						$url = $this->_baseUrlTodos;
					}
				}
				$time_start = microtime(true);
                if ($this->UseConnector()) {
				$response = \Httpful\Request::post($url)
					->sendsJson()
                            ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                            ->body(json_encode($data))
                            ->send();
                } else {
                    $response = \Httpful\Request::post($url)
                            ->sendsJson()
					->authenticateWith($this->_usernameRest, $this->_passwortRest)
					->body(json_encode($data))
					->send();
                }
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
				if ($response->hasErrors()) {
                    ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() Could not add to %s . HTTP Staus code %s", $folderid, $response->code));
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() Data \n%s\n", print_r($data, true)));
                    ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->ChangeMessageOnlineAkteRest() HTTP Status " . $response->code . "\r\n\r\nHeaders: " . $response->raw_headers . "\r\n\r\nBody: " . $response->raw_body . "\r\n\r\n");
					return false;
                } else {
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() returned body: %s", print_r($response->body, true)));
					return $response->body;
				}
            } else {
				$url = ONLINEAKTE_REST_URL_TERMINE . "/" . $data->FrNr;
				if ($this->UseConnector()) {
					//$url = RELAY_REST_URL_TERMINE . "?frnr=" . $data->FrNr;
					$url = $this->_baseUrlTermine . "?frnr=" . $data->FrNr;
				}
				if (substr($folderid, 0, strlen("tasks")) === "tasks") {
					$url = ONLINEAKTE_REST_URL_TODO . "/" . $data->FrNr;
					if ($this->UseConnector()) {
						//$url = RELAY_REST_URL_TODO . "?frnr=" . $data->FrNr;
						$url = $this->_baseUrlTodos . "?frnr=" . $data->FrNr;
					}
				}
				$time_start = microtime(true);
                if ($this->UseConnector()) {
				$response = \Httpful\Request::put($url)
					->sendsJson()
                            ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                            ->body(json_encode($data))
                            ->send();
                } else {
                    $response = \Httpful\Request::put($url)
                            ->sendsJson()
					->authenticateWith($this->_usernameRest, $this->_passwortRest)
					->body(json_encode($data))
					->send();
                }
				ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
				if ($response->hasErrors()) {
                    ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->ChangeMessageOnlineAkteRest() Could not modify Object in folder %s with id %s. HTTP Staus code %s", $folderid, $data->FrNr, $response->code));
                    ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->ChangeMessageOnlineAkteRest() HTTP Status " . $response->code . "\r\n\r\nHeaders: " . $response->raw_headers . "\r\n\r\nBody: " . $response->raw_body . "\r\n\r\n");
					return false;
                } else {
                    return $data->FrNr;
				}
			}
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->ChangeMessageOnlineAkteRest() Exception: " . $fault->getMessage());
            $this->ThrowAdvoNetException($fault);
		}
			}

	/**
	 * Change/Add a message with contents received from ActiveSync
	 * @see BackendDiff::ChangeMessage()
	 */
	public function ChangeMessageRest($folderid, $id, $message, $contentParameters) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->ChangeMessageRest('%s','%s')", $folderid,  $id));
        ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->changeMessageRest() $message=' . print_r($message, true));

		$data = $this->_ParseASToVCalendarRest($message, $folderid ); // $data ist ein array, kein objekt!

		if (!$this->UseConnector()) {
			if (substr($folderid, 0, strlen("tasks")) === "tasks") {
				$data["Kuerzel"] = $this->_kuerzel;
				$data["SB"] = $this->_kuerzel;
            } else {
				$data["Anwalt"] = $this->_kuerzel;
				$data["Sb"] = $this->_kuerzel;
			}
        } else {
			// connector
			$kuerzel = $this->getKuerzelFromFolderId($folderid);
			if (substr($folderid, 0, strlen("tasks")) === "tasks") {
				if (!empty($kuerzel)) {
					$data["Kuerzel"] = $kuerzel;
                } else {
					$data["Kuerzel"] = "Pool";
				}
				$data["SB"] = $this->_kuerzel;
            } else {
				$data["Anwalt"] = $kuerzel;
				$data["Sb"] = $this->_kuerzel;
			}
		}

		$data = $this->arrayToObject($data);
		$isNew = true;
		$dataOld = null;
		if ($id) {
			$isNew = false;
			$data->FrNr = (int)substr($id, 0, strpos($id,'@'));
			if (!array_key_exists($id, $this->_collection)) {
				$dataOld = $this->GetEntryByUidFromOnlineAkteRest($folderid, $id);
            } else {
				$dataOld = $this->_collection[$id];
			}
			if (array_key_exists('data', $dataOld[0])) {
				$dataOld = $dataOld[0]['data'];
			}
		}

		if (isset($dataOld->FrNr) && ($dataOld->FrNr == $data->FrNr)) {
			// Ändern
			if ($this->UseConnector()) {
				// Wenn der connector verwendet wird muss verhindert werden, dass private Termine anderer Mitarbeiter verändert werden.
				if (isset($dataOld->Privat) && ($dataOld->Privat == 1) && $this->_kuerzel != $dataOld->Anwalt) {
					$message = array();
					$message['id'] = $id;
					$message['flags'] = "1";
					$current_time = new DateTime();
					$message['mod'] = date_format($current_time, "Ymd\THis\Z");
					// Änderungen verwerfen und neue sync auslösen
					return $message;
				}
			}
            ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->changeMessageRest() $dataOld=' . print_r($dataOld, true));
			foreach ($data as $key => $value) {
				// Damit Werte, die in ActiveSync nicht geändert werden können, nicht verloren gehen
				//if ($folderid != 'tasks' && $key == "Text")
                if ((substr($folderid, 0, strlen("calendar")) === "calendar") && $key == "Text") {
					$text_backup = $dataOld->$key;
                }
				$dataOld->$key = $value;
			}
            if (isset($dataOld->UhrzeitBisTicks)) {
                unset($dataOld->UhrzeitBisTicks);
            } // Sonst werden Änderungen in Uhrzeitbis dadurch wieder überschrieben...
			// Bei Terminen mit Aktenverknüpfung Änderungen im Titel nur erlauben, wenn Verknüpfungstext unverändert
			//if ($folderid != 'tasks') {
			if (substr($folderid, 0, strlen("calendar")) === "calendar") {
				if (isset($dataOld->Nr)) {
					if ($dataOld->Nr > 0) {
						$azrubrum = "";
                        if (isset($dataOld->OptionalAttendees)) {
							$azrubrum = $dataOld->OptionalAttendees;
                        } elseif (isset($dataOld->AzRubrum)) {
							$azrubrum = $dataOld->AzRubrum;
                        }
						if (strlen($azrubrum) > 0) {
							$pos = strpos($dataOld->Text, " " . $azrubrum);
							if ($pos !== false) {
								$dataOld->Text = str_replace( " " . $azrubrum, "", $dataOld->Text);
                            } else {
								//Änderung verbieten und neue Sync auslösen
								$dataOld->Text = $text_backup;
								$dataOld->ZuletztGeaendertAm = gmdate("Y-m-d\TH:i:s\Z");
							}
						}
					}
				}
				if ($this->UseConnector()) {
					// Kürzel am Anfang des Termintitels wieder entfernen, sonst Duplizierung
					if (isset($dataOld->Anwalt)) {
						if (empty($dataOld->Anwalt) || ($dataOld->Anwalt == 'ALLE') || ($dataOld->Anwalt == 'Allgemein') ) {
							if (strpos($dataOld->Text, "ALLE/") === 0) {
								$dataOld->Text = str_replace( "ALLE/", "", $dataOld->Text);
							}
                        } else {
							if (strpos($dataOld->Text, $dataOld->Anwalt . "/") === 0) {
								$dataOld->Text = str_replace( $dataOld->Anwalt . "/", "", $dataOld->Text);
							}
						}
					}
				}
			}
            ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->changeMessageRest() $data=' . print_r($dataOld, true));
			if ($this->ChangeMessageOnlineAkteRest($dataOld, $folderid, false)) {
				$item = array();
                if (substr($folderid, 0, strlen("calendar")) === "calendar") {
					$item['href'] = $dataOld->FrNr . "@" . $this->_frnrSuffix;
                } else {
					$item['href'] = $dataOld->FrNr . "@TODO." . $this->_frnrSuffix;
                }
				$item['data'] = $dataOld;
				$this->_collection[$id] = $item;
			}
        } else {
			// Neu anlegen
			$idNew = $this->ChangeMessageOnlineAkteRest($data, $folderid, true);
			if ($idNew) {
                if (substr($folderid, 0, strlen("calendar")) === "calendar") {
					$id = $idNew . "@" . $this->_frnrSuffix;
                } else {
					$id = $idNew . "@TODO." . $this->_frnrSuffix;
                }
				$item = array();
				$item['href'] = $id;
				$item['data'] = $data;
				$this->_collection[$id] = $item;
			}
		}
		return $this->StatMessage($folderid, $id);
	}

	private function arrayToObject( $array ) {
		$object = new stdClass();
		foreach( $array as $key => $value ) {
			if( is_array( $value ) ) {
				$object->$key = arrayToObject( $value );
            } else {
				$object->$key = $value;
			}
		}
		return $object;
	}

        /**
	 * Change/Add a message with contents received from ActiveSync
	 * @see BackendDiff::ChangeMessage()
	 */
    public function ChangeMessage($folderid, $id, $message, $contentParameters) {
		return $this->ChangeMessageRest($folderid, $id, $message, $contentParameters);
	}

	/**
	 * Change the read flag is not supported.
	 * @see BackendDiff::SetReadFlag()
	 */
    public function SetReadFlag($folderid, $id, $flags, $contentParameters) {
		return false;
	}

	function DeleteMessageOnlineAkteRest( $frnr, $folderid ) {
		//$id = substr($uid, 0, strpos($uid,'@'));
		try {
			$url = "";
			if (substr($folderid, 0, strlen("calendar")) === "calendar") {
				$url = ONLINEAKTE_REST_URL_TERMINE . "/" . $frnr;
                if ($this->UseConnector()) {
					$url = $this->_baseUrlTermine . "?frnr=" . $frnr;
			}
            } else {
				$url = ONLINEAKTE_REST_URL_TODO . "/" . $frnr;
                if ($this->UseConnector()) {
					$url = $this->_baseUrlTodos . "?frnr=" . $frnr;
			}
            }
			$time_start = microtime(true);
            if ($this->UseConnector()) {
			$response = \Httpful\Request::delete($url)
                        ->addHeader('Authorization', 'Bearer ' . $this->_access_token)
                        ->send();
            } else {
                $response = \Httpful\Request::delete($url)
				->authenticateWith($this->_usernameRest, $this->_passwortRest)
				->send();
            }
			ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->DeleteMessageOnlineAkteRest() Executiontime: %f seconds", microtime(true) - $time_start));
			if ($response->hasErrors()) {
                ZLog::Write(LOGLEVEL_ERROR, sprintf("BackendOnlineAkte->DeleteMessageOnlineAkteRest() Could not delete object with frnr %s in folder %s. HTTP Staus code %s", $frnr, $folderid, $response->code));
                ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->DeleteMessageOnlineAkteRest() HTTP Status " . $response->code . "\r\n\r\nHeaders: " . $response->raw_headers . "\r\n\r\nBody: " . $response->raw_body . "\r\n\r\n");
				return false;
			}
        } catch (Exception $fault) {
            ZLog::Write(LOGLEVEL_ERROR, "BackendOnlineAkte->DeleteMessageOnlineAkteRest() Exception: " . $fault->getMessage());
			return false;
		}
        ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->DeleteMessageOnlineAkteRest() frnr=%s folder=%s", $frnr, $folderid));
		return true;
	}

	/**
	 * Delete a message from the OnlineAkte server.
	 * @see BackendDiff::DeleteMessage()
	 */
    public function DeleteMessage($folderid, $id, $contentParameters) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->DeleteMessage('%s','%s')", $folderid,  $id));
        if (substr($folderid, 0, strlen("tasks")) === "tasks") {
			// Nix zu tun
        } else {
            if (strpos($id, '.VORTERMIN') > 0 || strpos($id, '.HINFAHRT') > 0 || strpos($id, '.RUECKFAHRT') > 0 || strpos($id, '.URLAUB') > 0) {
                return false;
		}
            if (strpos($id, "-") === 0) {
				return false;
		}
        }

		// Das kann vorkommen bei ignorierten Terminen / Aufgaben die nur auf dem Geraet aber nicht in Advoware existieren:
        if (strpos($id, '@') <= 0) {
			return true;
        }

		return $this->DeleteMessageOnlineAkteRest(substr($id, 0, strpos($id,'@')), $folderid);
	}

	/**
	 * Move a message is not supported by OnlineAkte.
	 * @see BackendDiff::MoveMessage()
	 */
    public function MoveMessage($folderid, $id, $newfolderid, $contentParameters) {
		return false;
	}

	private function _ParseVEventToASRest($data, $contentparameters) {
		//ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseVTodoToAS(): Parsing VTodo: " . print_r($data, true)));
		$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());

		$message = new SyncAppointment();
		$message->timezone = $this->_GetTimezoneString("Europe/Berlin");
		//$ical = new iCalComponent($data);
		//$vtodos = $ical->GetComponents("VTODO");
		//Should only loop once
		//foreach ($vtodos as $vtodo)
		//{
		//	$message = $this->_ParseVTodoToSyncObject($vtodo, $message, $truncsize);
		//}
                //ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->_ParseVTodoToAS(): $message: ' . print_r($message, true));
		$message = $this->_ParseVEventToSyncObjectRest($data, $message, $truncsize);
		return $message;
	}

	/**
	 * Generate a TodoModel or Terminmodel VCalendar from ActiveSync object.
	 * @param string $data
	 * @param string $folderid
	 * @param string $id
	 */
	private function _ParseASToVCalendarRest($data, $folderid) {
		if (substr($folderid, 0, strlen("calendar")) === "calendar") {
			$termin = $this->_ParseASEventToVEventRest($data);
			return $termin;
        } else if (substr($folderid, 0, strlen("tasks")) === "tasks") {
			$event = $this->_ParseASTaskToVTodoRest($data);
			return $event;
		}
	}

	/**
	 * Convert a iCAL VTodo to ActiveSync format
	 * @param string $data
	 * @param ContentParameters $contentparameters
	 */
    private function _ParseVTodoToASRest($data, $contentparameters) {
		//ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseVTodoToAS(): Parsing VTodo: " . print_r($data, true)));
		$truncsize = Utils::GetTruncSize($contentparameters->GetTruncation());

		$message = new SyncTask();
		//$ical = new iCalComponent($data);
		//$vtodos = $ical->GetComponents("VTODO");
		//Should only loop once
		//foreach ($vtodos as $vtodo)
		//{
		//	$message = $this->_ParseVTodoToSyncObject($vtodo, $message, $truncsize);
		//}
                //ZLog::Write(LOGLEVEL_DEBUG, 'BackendOnlineAkte->_ParseVTodoToAS(): $message: ' . print_r($message, true));
		$message = $this->_ParseVTodoToSyncObjectRest($data, $message, $truncsize);
		return $message;
	}

	private function _ParseVEventToSyncObjectRest($vevent, $message, $truncsize) {
		//ZLog::Write(LOGLEVEL_INFO, "vevent=\n" . print_r($vevent, true) );
		//Defaults
		$message->busystatus = "2";
		$message->meetingstatus = 0;

		if (isset($vevent->Text)) {
			$message->subject = $vevent->Text;
		}
		if (isset($vevent->Nr)) {
			if ($vevent->Nr > 0) {
				if (isset($vevent->OptionalAttendees)) {
					if (strlen($vevent->OptionalAttendees) > 0) {
						$message->subject = $message->subject . " " . $vevent->OptionalAttendees;
					}
                } else if (isset($vevent->AzRubrum)) {
					if (strlen($vevent->AzRubrum) > 0) {
						$message->subject = $message->subject . " " . $vevent->AzRubrum;
					}
				}
			}
		}
		if ($this->UseConnector()) {
			if (isset($vevent->Anwalt)) {
				// Kürzel am Anfang des Titels anzeigen wie in advoware
				if (empty($vevent->Anwalt)  || ($vevent->Anwalt == 'ALLE') || ($vevent->Anwalt == 'Allgemein') ) {
					$message->subject = "ALLE/" . $message->subject;
                } else {
					$message->subject = $vevent->Anwalt . "/" . $message->subject;
				}
			}
		}
		if (isset($vevent->ZuletztGeaendertAm)) {
			$message->dtstamp = $this->_MakeUTCDateRest($vevent->ZuletztGeaendertAm);
		}
		if (isset($vevent->Datum)) {
			$message->starttime = $this->_MakeUTCDateRest($vevent->Datum);
		}
		if (isset($vevent->DatumBis)) {
			ZLog::Write(LOGLEVEL_DEBUG, "vevent->DatumBis=" . print_r($vevent->DatumBis, true) . " vevent->UhrzeitBis=" . print_r($vevent->UhrzeitBis, true) . "\n");
			$message->endtime = $this->_MakeUTCDateRest(substr($vevent->DatumBis, 0, 11) . $vevent->UhrzeitBis . "Z");
			ZLog::Write(LOGLEVEL_DEBUG, "message->endtime=" . print_r($message->endtime, true) . "\n");
		}
		if (isset($vevent->Abwesenheit)) {
			if ($vevent->Abwesenheit == 1) {
				$message->alldayevent = "1";
//                        if (isset($vevent->Datum) && isset($vevent->DatumBis)) {
//                            $message->starttime = $this->_MakeUTCDateRest(substr($vevent->Datum, 0, 11) . "12:00:00");
//                            $message->endtime = $this->_MakeUTCDateRest(substr($vevent->DatumBis, 0, 11) . "12:00:00");
//                        }
			}
		}
		if (isset($vevent->FrNr)) {
//                    if (isset($vevent->Special)) {
//                        if ($vevent->Special == 1)
//                            $message->uid = $vevent->FrNr . ".VORTERMIN@" . $this->_usernameRest;
//                        elseif ($vevent->Special == 2)
//                            $message->uid = $vevent->FrNr . ".HINFAHRT@" . $this->_usernameRest;
//                        elseif ($vevent->Special == 3)
//                            $message->uid = $vevent->FrNr . ".RUECKFAHRT@" . $this->_usernameRest;
//                        elseif ($vevent->Special == 4)
//                            $message->uid = $vevent->FrNr . ".URLAUB@" . $this->_usernameRest;
//                        else 
//                            $message->uid = $vevent->FrNr . "@" . $this->_usernameRest;
//                    }
//                    else
			$message->uid = $vevent->FrNr . "@" . $this->_frnrSuffix;
		}
		if (isset($vevent->Ort)) {
			$message->location = $vevent->Ort;
		}
		if (isset($vevent->Privat)) {
			if ($vevent->Privat == 1) {
				$message->sensitivity = "2";
            } else {
				$message->sensitivity = "0";
			}
		}
		if (isset($vevent->Notiz)) {
			$body = $vevent->Notiz;
			$body = str_replace("\n","\r\n", str_replace("\r","",$body));
			if (Request::GetProtocolVersion() >= 12.0) {
				$message->asbody = new SyncBaseBody();
				$message->asbody->type = SYNC_BODYPREFERENCE_PLAIN;
				if ($truncsize > 0 && $truncsize < strlen($body)) {
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseVEventToSyncObjectRest() truncsize '%d' msglen '%d'", $truncsize, strlen($body)));
					$message->asbody->truncated = 1;
					$message->asbody->data = StringStreamWrapper::Open(Utils::Utf8_truncate($body, $truncsize));
					$message->asbody->estimatedDataSize = strlen(Utils::Utf8_truncate($body, $truncsize));
                } else {
                    ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseVEventToSyncObjectRest() NOT TRUNCATED '%d' msglen '%d'", $truncsize, strlen($body)));
					//$message->asbody->truncated = 0;
					$message->asbody->data = StringStreamWrapper::Open($body);
					$message->asbody->estimatedDataSize = strlen($body);
				}
            } else {
				$message->body = $body;
				if ($truncsize > 0 && $truncsize < strlen($message->body)) {
					$message->bodytruncated = 1;
					$message->body = Utils::Utf8_truncate($message->body, $truncsize);
                } else {
					$message->bodytruncated = 0;
				}
				$message->bodysize = strlen($message->body);
			}
		}
		if (isset($vevent->Dauertermin) && ($vevent->Dauertermin == 1)) {
			if (isset($vevent->Turnus) && isset($vevent->TurnusArt) && ($vevent->Turnus > 0) && ($vevent->TurnusArt > 0)) {
				$recurrence = new SyncRecurrence();
				switch ($vevent->TurnusArt) {
					case 1: //täglich
						$recurrence->type = "0";
						break;
					case 2: //wöchentlich
						$recurrence->type = "1";
						if (isset($vevent->Datum)) {
							switch (date("w", date_create_from_format('Y-m-d', substr($vevent->Datum, 0, 10), timezone_open("UTC"))->getTimeStamp())) {
								//   1 = Sunday
								//   2 = Monday
								//   4 = Tuesday
								//   8 = Wednesday
								//  16 = Thursday
								//  32 = Friday
								//  62 = Weekdays  // not in spec: daily weekday recurrence
								//  64 = Saturday
								case "0": //sunday
									$recurrence->dayofweek = 1;
									break;
								case "1": //mondy
									$recurrence->dayofweek = 2;
									break;
								case "2": //tuesday
									$recurrence->dayofweek = 4;
									break;
								case "3": //wednesday
									$recurrence->dayofweek = 8;
									break;
								case "4": //thursday
									$recurrence->dayofweek = 16;
									break;
								case "5": //friday
									$recurrence->dayofweek = 32;
									break;
								case "6": //saturday
									$recurrence->dayofweek = 64;
									break;
							}
						}
						break;
					case 3: //monatlich
						$recurrence->type = "2";
                        if (isset($vevent->Datum)) {
							$recurrence->dayofmonth = (int)substr($vevent->Datum, 8, 2);
                        }
						break;
					case 4: //jährlich
						$recurrence->type = "5";
                        if (isset($vevent->Datum)) {
							$recurrence->dayofmonth = (int)substr($vevent->Datum, 8, 2);
                        }
						break;
				}
				if (isset($vevent->DatumBis)) {
					if (isset($vevent->UhrzeitBis)) {
						$recurrence->until = $this->_MakeUTCDateRest(substr($vevent->DatumBis, 0, 11) . $vevent->UhrzeitBis);
                    } else {
						$recurrence->until = $this->_MakeUTCDateRest(substr($vevent->DatumBis, 0, 11) . '23:59:59');
					}
				}
				$recurrence->interval = $vevent->Turnus;
				// Endzeit anpassen
				if (isset($vevent->Datum)) {
					if (isset($vevent->UhrzeitBis)) {
						$message->endtime = $this->_MakeUTCDateRest(substr($vevent->Datum, 0, 11) . $vevent->UhrzeitBis );
					}
				}
				$message->recurrence = $recurrence;
			}
		}
		if (isset($vevent->Erinnerung)) {
			if ($vevent->Erinnerung == 1) {
				if (isset($vevent->ErinnerungsDatum)) {
					$trigger = date_create("@" . $this->_MakeUTCDateRest($vevent->ErinnerungsDatum));
					$begin = date_create("@" . $message->starttime);
					$interval = date_diff($begin, $trigger);
					$message->reminder = $interval->format("%i") + $interval->format("%h") * 60 + $interval->format("%a") * 60 * 24;
				}
			}
		}

	return $message;
	}

        /**
	 * Parse 1 VEvent
	 * @param ical_vtodo $vtodo
	 * @param SyncAppointment(Exception) $message
	 * @param int $truncsize
	 */
    private function _ParseVTodoToSyncObjectRest($vtodo, $message, $truncsize) {
		//Default
		$message->reminderset = "0";
		$message->importance = "1";
		$message->complete = "0";
		if (isset($vtodo->Text)) {
			$message->subject = $vtodo->Text;
		}
		if (isset($vtodo->Status)) {
			switch ($vtodo->Status) {
				case "offen":
				case "in Bearbeitung":
				case "delegiert":
					$message->complete = "0";
					break;
				case "zurückgestellt":
				case "erledigt":
					$message->complete = "1";
					break;
			}
		}
		if (isset($vtodo->Erledigt_Datum)) {
			if ($vtodo->Erledigt_Datum != "0001-01-01T00:00:00Z") {
				$message->datecompleted = $this->_MakeUTCDateRest($vtodo->Erledigt_Datum);
			}
		}
		if (isset($vtodo->Datum)) {
			$message->utcduedate = $this->_MakeUTCDateRest($vtodo->Datum);
		}
		if (isset($vtodo->Prioritaet)){
			switch ($vtodo->Prioritaet) {
				case "Hoch":
					$message->importance = "2"; // High
					break;
				case "Niedrig":
					$message->importance = "0"; // Low
					break;
				default:
					$message->importance = "1"; // Normal
					break;
			}
		}

		// ToDo: Für später: Wiederholungen von Aufgaben

		if (isset($vtodo->Privat)) {
			if ($vtodo->Privat == "1") {
				$message->sensitivity = "2";
            } else {
				$message->sensitivity = "0";
			}
		}

		if (isset($vtodo->Notizen)) {
			$body = $vtodo->Notizen;
			$body = str_replace("\n","\r\n", str_replace("\r","",$body));
			if (Request::GetProtocolVersion() >= 12.0) {
				$message->asbody = new SyncBaseBody();
				$message->asbody->type = SYNC_BODYPREFERENCE_PLAIN;
				if ($truncsize > 0 && $truncsize < strlen($message->asbody->data)) {
					$message->asbody->truncated = 1;
					$message->asbody->data = StringStreamWrapper::Open(Utils::Utf8_truncate($body, $truncsize));
					$message->asbody->estimatedDataSize = strlen(Utils::Utf8_truncate($body, $truncsize));
                } else {
					//$message->asbody->truncated = 0;
					$message->asbody->data = StringStreamWrapper::Open($body);
					$message->asbody->estimatedDataSize = strlen($body);
				}
            } else {
				$message->body = $body;
				if ($truncsize > 0 && $truncsize < strlen($message->body)) {
					$message->bodytruncated = 1;
					$message->body = Utils::Utf8_truncate($message->body, $truncsize);
                } else {
					$message->bodytruncated = 0;
				}
				$message->bodysize = strlen($message->body);
			}
		}

		if (isset($vtodo->ErinnerungsDatum)) {
			if ($vtodo->ErinnerungsDatum != "0001-01-01T00:00:00Z") {
				$message->remindertime = $this->_MakeUTCDateRest($vtodo->ErinnerungsDatum);
				$message->reminderset = "1";
			}
		}
		return $message;
	}

	private function _ParseASEventToVEventRest($data) {
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest data=\n%s\n", print_r($data, true)), false );
		$termin = array();
		$termin["ZuletztGeaendertAm"] = gmdate("Y-m-d\TH:i:s\Z");
		$termin["Abwesenheit"] = 0;
		$termin["Ort"] = "";

        if (isset($data->dtstamp)) {
			$termin["ZuletztGeaendertAm"] = gmdate("Y-m-d\TH:i:s\Z", $data->dtstamp);
		}
		if (isset($data->alldayevent)) {
            if ($data->alldayevent == '1') {
				$termin["Abwesenheit"] = 1;
		}
        }
        if (isset($data->starttime)) {
//                if ($termin["Abwesenheit"] == 1)
//                    $termin["Datum"] = gmdate("Y-m-d", $data->starttime) . "T00:00:00";
//                else 
//                    $termin["Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->starttime);
			$termin["Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->starttime);
		}
        if (isset($data->subject)) {
			$termin["Text"] = $data->subject;
		}
//            if (isset($data->organizeremail))
//            {
//                    if (isset($data->organizername))
//                    {
//                            $vevent->AddProperty("ORGANIZER", sprintf("MAILTO:%s", $data->organizeremail), array("CN" => $data->organizername));
//                    }
//                    else
//                    {
//                            $vevent->AddProperty("ORGANIZER", sprintf("MAILTO:%s", $data->organizeremail));
//                    }
//            }
        if (isset($data->location)) {
			$termin["Ort"] = $data->location;
        }
        if (isset($data->endtime)) {
			if ($termin["Abwesenheit"] == 1) {
				$termin["DatumBis"] = gmdate("Y-m-d\TH:i:s\Z", $data->endtime - 60); // minus eine Minute weil das in AW so gespeichert wird
				$termin["UhrzeitBis"] = gmdate("H:i:s", $data->endtime - 60); // minus eine Minute weil das in AW so gespeichert wird
            } else {
				$termin["DatumBis"] = gmdate("Y-m-d\TH:i:s\Z", $data->endtime);
				$termin["UhrzeitBis"] = gmdate("H:i:s", $data->endtime);
			}
		}
        if (isset($data->recurrence)) {
			If (isset($data->starttime) && isset($data->endtime)) {
				if (($data->endtime - $data->starttime) <= 86340) { // 23:59:00 längste Zeit für sich Wiederholende Termine in AW
					if (isset($data->recurrence->type)) {
						switch ($data->recurrence->type) {
							case "0":
								$termin["TurnusArt"] = 1;
								break;
							case "1":
								$termin["TurnusArt"] = 2;
								break;
							case "2":
								$termin["TurnusArt"] = 3;
								break;
							case "5":
								$termin["TurnusArt"] = 4;
								break;
						}
					}
					if (isset($termin["TurnusArt"])) {
						if (isset($data->recurrence->until)) {
							// Problem: Wenn die Endzeit des Termins und die Endzeit der Wiederholung in unterschiedlichen Sommer/Winterzeit-Bereichen liegen
							$until = date_create('@' . $data->recurrence->until);
							date_timezone_set($until, timezone_open('Europe/Berlin'));
							ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest until=", print_r($until, true)), false );

							$end = date_create('@' . $data->endtime);
							date_timezone_set($end, timezone_open('Europe/Berlin'));
							ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest end=", print_r($until, true)), false );

							$offset_until = date_offset_get($until); // in Sekunden
							$offset_end = date_offset_get($end);
							ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest offset_until=", print_r($offset_until, true)), false );
							ZLog::Write(LOGLEVEL_INFO, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest offset_end=", print_r($offset_end, true)), false );
							If ($offset_until != $offset_end) {
								// UhrzeitBis anpassen. Macht Probleme wenn der ProviderSvc mit einer anderen Zeitzone als Europe/Berlin arbeitet
								$termin["UhrzeitBis"] = gmdate("H:i:s", $data->endtime - ($offset_until - $offset_end));
							}
							$termin["DatumBis"] = gmdate("Y-m-d", $data->recurrence->until) . gmdate("\TH:i:s\Z", $data->endtime);
							$termin["Dauertermin"] = 1;
                        } else {
							$count = 0;
							if (isset($data->recurrence->occurrences)) {
								$count = $data->recurrence->occurrences;
							}
							if ($count >= 2) {
							// ToDO: Hier muss noch irgendwie die Endzeit gesetzt werden
                            } else {
								// Wiederholung ohne Enddatem (=für immer)
								// größtes Datum in 32 Bit Unix Timestamp ist 2038-1-19T03:14:08Z
								// Wir setzen nur das Jahr auf 2037 und lassen den Rest um Probleme mit Sommer/Winterzeit zu vermeiden
								$termin["DatumBis"] = "2037-" . gmdate("m-d\TH:i:s\Z", $data->endtime);
								$termin["Dauertermin"] = 1;
								$termin["UhrzeitBis"] = gmdate("H:i:s", $data->endtime);
							}
						}
	//                    if (isset($rec->occurrences))
	//                    {
	//                            $rrule[] = "COUNT=" . $rec->occurrences;
	//                    }
						if (isset($data->recurrence->interval)) {
							if ($this->UseConnector()) {
								// connector Dtos benötigen int
								$termin["Turnus"] = intval($data->recurrence->interval);
                            } else {
								$termin["Turnus"] = $data->recurrence->interval;
							}
                        } else {
							$termin["Turnus"] = 1;
						}
	//                    if (isset($data->recurrence->dayofweek)) {
	//                            $days = array();
	//                            if (($data->recurrence->dayofweek & 1) == 1)
	//                            {
	//                                    $days[] = "SU";
	//                            }
	//                            if (($data->recurrence->dayofweek & 2) == 2)
	//                            {
	//                                    $days[] = "MO";
	//                            }
	//                            if (($data->recurrence->dayofweek & 4) == 4)
	//                            {
	//                                    $days[] = "TU";
	//                            }
	//                            if (($data->recurrence->dayofweek & 8) == 8)
	//                            {
	//                                    $days[] = "WE";
	//                            }
	//                            if (($data->recurrence->dayofweek & 16) == 16)
	//                            {
	//                                    $days[] = "TH";
	//                            }
	//                            if (($data->recurrence->dayofweek & 32) == 32)
	//                            {
	//                                    $days[] = "FR";
	//                            }
	//                            if (($data->recurrence->dayofweek & 64) == 64)
	//                            {
	//                                    $days[] = "SA";
	//                            }
	//                            $rrule[] = "BYDAY=" . implode(",", $days);
	//                    }
	//                    if (isset($data->recurrence->dayofmonth))
	//                    {
	//                            $rrule[] = "BYMONTHDAY=" . $data->recurrence->dayofmonth;
	//                    }
	//                    if (isset($data->recurrence->monthofyear))
	//                    {
	//                            $rrule[] = "BYMONTH=" . $data->recurrence->monthofyear;
	//                    }
					}
				}
			}
		}
		if (isset($data->sensitivity)) {
			switch ($data->sensitivity) {
				case "0":
				case "1":
					$termin["Privat"] = 0;
					break;
				case "2":
				case "3":
					$termin["Privat"] = 1;
					break;
			}
		}
//            if (isset($data->busystatus)) {
//                    switch ($data->busystatus) {
//                            case "0":
//                            case "1":
//                                    $vevent->AddProperty("TRANSP", "TRANSPARENT");
//                                    break;
//                            case "2":
//                            case "3":
//                                    $vevent->AddProperty("TRANSP", "OPAQUE");
//                                    break;
//                    }
//            }
		if (isset($data->reminder)) {
			if (isset($data->starttime)) {
				$date = date_create('@' . $data->starttime);
				$date->sub(new DateInterval('PT' . $data->reminder . 'M'));
				$termin["ErinnerungsDatum"] = date_format($date, "Y-m-d\TH:i:s\Z");
				$termin["Erinnerung"] = 1;
			}
		}
//            if (isset($data->meetingstatus)) {
//                    switch ($data->meetingstatus) {
//                            case "1":
//                                    $vevent->AddProperty("STATUS", "TENTATIVE");
//                                    break;
//                            case "3":
//                                    $vevent->AddProperty("STATUS", "CONFIRMED");
//                                    break;
//                            case "5":
//                            case "7":
//                                    $vevent->AddProperty("STATUS", "CANCELLED");
//                                    break;
//                    }
//            }
//            if (isset($data->attendees) && is_array($data->attendees)) {
//                    //If there are attendees, we need to set ORGANIZER
//                    //Some phones doesn't send the organizeremail, so we gotto get it somewhere else.
//                    //Lets use the login here ($username)
//                    if (!isset($data->organizeremail)) {
//                            $vevent->AddProperty("ORGANIZER", sprintf("MAILTO:%s", $this->_username));
//                    }
//                    foreach ($data->attendees as $att) {
//                            $att_str = sprintf("MAILTO:%s", $att->email);
//                            $vevent->AddProperty("ATTENDEE", $att_str, array("CN" => $att->name));
//                    }
//            }
		$body = null;
		if (isset($data->rtf)) {
			$rtfparser = new rtf();
			$rtfparser->loadrtf(base64_decode($data->rtf));
			$rtfparser->output("ascii");
			$rtfparser->parse();
			$body = $rtfparser->out;
        } elseif (isset($data->body)) {
			$body = $data->body;
			//ZLog::Write(LOGLEVEL_DEBUG, "LLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL DESCRIPTION: " . str_replace ( "\n", "\r\n", str_replace ( "\r", "", $data->body ) ) );
        } elseif (isset($data->asbody)) {
			if (isset($data->asbody->data)) {
				$body = stream_get_contents($data->asbody->data);
			}
		}
		if (isset($body)) {
			if (strpos($body, "\n") === false) {
				$body = str_replace("\r", "\r\n", $body);
            } else {
				$body = str_replace ( "\n", "\r\n", str_replace ( "\r", "", $body ) );
			}
			$termin["Notiz"] = $body;
		}
//            if (isset($data->categories) && is_array($data->categories)) {
//                    $vevent->AddProperty("CATEGORIES", implode(",", $data->categories));
//            }
		ZLog::Write(LOGLEVEL_DEBUG, sprintf("BackendOnlineAkte->_ParseASEventToVEventRest termin=\n%s\n", print_r($termin, true)), false );
		return $termin;
	}

        /**
	 * Generate a VTODO from a SyncAppointment(Exception)
	 * @param string $data
	 * @param string $id
	 * @return iCalComponent
	 */
	private function _ParseASTaskToVTodoRest($data) {
		$vtodo = array();
		$vtodo["ZuletztGeaendertAm"] = gmdate("Y-m-d\TH:i:s\Z");
		$body = null;
        if (isset($data->asbody)) {
            if (isset($data->asbody->data)) {
				$body = stream_get_contents($data->asbody->data);
				//$vtodo["Notizen"] = $data->asbody->data;
			}
        } elseif (isset($data->body)) {
			//$vtodo->AddProperty("DESCRIPTION", $data->body);
            $body = $data->body;
			//$vtodo["Notizen"] = $body_temp;
			//ZLog::Write(LOGLEVEL_DEBUG, "LLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL DESCRIPTION: " . str_replace ( "\n", "\r\n", str_replace ( "\r", "", $data->body ) ) );
			//ZLog::Write(LOGLEVEL_DEBUG, 'LLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL $data->body: ' . print_r($data->body, true));
			//ZLog::Write(LOGLEVEL_DEBUG, 'LLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLLL $body_temp: ' . print_r($body_temp, true));
		}
		if (isset($body)) {
			if (strpos($body, "\n") === false) {
				$body = str_replace("\r", "\r\n", $body);
            } else {
				$body = str_replace ( "\n", "\r\n", str_replace ( "\r", "", $body ) );
			}
			$vtodo["Notizen"] = $body;
		}
        if (isset($data->complete)) {
            if ($data->complete == "0") {
				$vtodo["Status"] = "offen";
				$vtodo["Erledigt"] = "";
            } else {
				$vtodo["Status"] = "erledigt";
				$vtodo["Erledigt"] = "1";
			}
		}
        if (isset($data->datecompleted)) {
			$vtodo["Erledigt_Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->datecompleted);
		}
        if ($data->utcduedate) {
			$vtodo["Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->utcduedate);
		}
        if (isset($data->importance)) {
            if ($data->importance == "2") { // High
				$vtodo["Prioritaet"] = "Hoch";
            } elseif ($data->importance == "0") { // Low
				$vtodo["Prioritaet"] = "Niedrig";
            } else {
				$vtodo["Prioritaet"] = "Normal";
			}
		}
//            if (isset($data->recurrence))
//            {
//                    $vtodo->AddProperty("RRULE", $this->_GenerateRecurrence($data->recurrence));
//            }
        if ($data->reminderset && $data->remindertime) {
			$vtodo["ErinnerungsDatum"] = gmdate("Y-m-d\TH:i:s\Z", $data->remindertime);
			if (!isset($vtodo["Datum"])) {
				$vtodo["Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->remindertime);
			}
		}

		if (!isset($vtodo["Datum"])) {
			$vtodo["Datum"] = gmdate("Y-m-d\TH:i:s\Z");
		}

        if (isset($data->sensitivity)) {
            switch ($data->sensitivity) {
				case "0":
					$vtodo["Privat"] = "0";
						break;

				case "2":
				case "3":
					$vtodo["Privat"] = "1";
					break;
			}
		}
        if (isset($data->utcstartdate)) {
			if (!isset($vtodo["Datum"])) {
				$vtodo["Datum"] = gmdate("Y-m-d\TH:i:s\Z", $data->utcstartdate);
			}
		}
        if (isset($data->subject)) {
			$vtodo["Text"] = $data->subject;
		}
        if (isset($data->rtf)) {
			$rtfparser = new rtf();
			$rtfparser->loadrtf(base64_decode($data->rtf));
			$rtfparser->output("ascii");
			$rtfparser->parse();
			$vevent->AddProperty("DESCRIPTION", $rtfparser->out);
			$vtodo["Notizen"] = $rtfparser->out;
		}

		return $vtodo;
	}

	/**
	 * Generate date object from string and timezone.
	 * @param string $value "2016-08-07T15:27:27"
	 * @param string $timezone
	 */
    private function _MakeUTCDateRest($value, $timezone = null) {
		$tz = null;
        if ($timezone) {
			$tz = timezone_open($timezone);
		}
        if (!$tz) {
			//If there is no timezone set, we use the default timezone
			$tz = timezone_open(date_default_timezone_get());
		}
		//20110930T090000Z
		$date = date_create_from_format('Y-m-d\TH:i:s', $value, timezone_open("UTC"));
        if (!$date) {
			$date = date_create_from_format('Y-m-d\TH:i:s.u', $value, timezone_open("UTC"));
		}
        if (!$date) {
			$date = date_create_from_format('Y-m-d\TH:i:s.u\Z', $value, timezone_open("UTC"));
		}
        if (!$date) {
			$date = date_create_from_format('Y-m-d\TH:i:s\Z', $value, timezone_open("UTC"));
		}
        if (!$date) {
			// aeltere php / ubuntu können nur microseconds mit genau 6 Stellen
			$date = date_create_from_format('Y-m-d\TH:i:s\Z', substr($value, 0, strpos($value, '.')) . 'Z', timezone_open("UTC"));
		}
        if (!$date) {
			//20110930T090000
			$date = date_create_from_format('Ymd\THis', $value, $tz);
		}
        if (!$date) {
			//20110930 (Append T000000Z to the date, so it starts at midnight)
			$date = date_create_from_format('Ymd\THis\Z', $value . "T000000Z", $tz);
		}
		return date_timestamp_get($date);
	}

	/**
	 * Generate ActiveSync Timezone Packed String.
	 * @param string $timezone
	 * @param string $with_names
	 * @throws Exception
	 */
    private function _GetTimezoneString($timezone, $with_names = true) {
		// UTC needs special handling
        if ($timezone == "UTC") {
			return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0));
        }
		try {
			//Generate a timezone string (PHP 5.3 needed for this)
			$timezone = new DateTimeZone($timezone);
			$trans = $timezone->getTransitions(time());
			$stdTime = null;
			$dstTime = null;
            if (count($trans) < 3) {
				throw new Exception();
			}
            if ($trans[1]['isdst'] == 1) {
				$dstTime = $trans[1];
				$stdTime = $trans[2];
            } else {
				$dstTime = $trans[2];
				$stdTime = $trans[1];
			}
			$stdTimeO = new DateTime($stdTime['time']);
			$stdFirst = new DateTime(sprintf("first sun of %s %s", $stdTimeO->format('F'), $stdTimeO->format('Y')), timezone_open("UTC"));
			$stdBias = $stdTime['offset'] / -60;
			$stdName = $stdTime['abbr'];
			$stdYear = 0;
			$stdMonth = $stdTimeO->format('n');
			$stdWeek = floor(($stdTimeO->format("j")-$stdFirst->format("j"))/7)+1;
			$stdDay = $stdTimeO->format('w');
			$stdHour = $stdTimeO->format('H');
			$stdMinute = $stdTimeO->format('i');
			$stdTimeO->add(new DateInterval('P7D'));
            if ($stdTimeO->format('n') != $stdMonth) {
				$stdWeek = 5;
			}
			$dstTimeO = new DateTime($dstTime['time']);
			$dstFirst = new DateTime(sprintf("first sun of %s %s", $dstTimeO->format('F'), $dstTimeO->format('Y')), timezone_open("UTC"));
			$dstName = $dstTime['abbr'];
			$dstYear = 0;
			$dstMonth = $dstTimeO->format('n');
			$dstWeek = floor(($dstTimeO->format("j")-$dstFirst->format("j"))/7)+1;
			$dstDay = $dstTimeO->format('w');
			$dstHour = $dstTimeO->format('H');
			$dstMinute = $dstTimeO->format('i');
			$dstTimeO->add(new DateInterval('P7D'));
            if ($dstTimeO->format('n') != $dstMonth) {
				$dstWeek = 5;
			}
			$dstBias = ($dstTime['offset'] - $stdTime['offset']) / -60;
            if ($with_names) {
				return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', $stdBias, $stdName, 0, $stdMonth, $stdDay, $stdWeek, $stdHour, $stdMinute, 0, 0, 0, $dstName, 0, $dstMonth, $dstDay, $dstWeek, $dstHour, $dstMinute, 0, 0, $dstBias));
            } else {
				return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', $stdBias, '', 0, $stdMonth, $stdDay, $stdWeek, $stdHour, $stdMinute, 0, 0, 0, '', 0, $dstMonth, $dstDay, $dstWeek, $dstHour, $dstMinute, 0, 0, $dstBias));
			}
        } catch (Exception $e) {
			// If invalid timezone is given, we return UTC
			return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0));
		}
		return base64_encode(pack('la64vvvvvvvvla64vvvvvvvvl', 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, 0, 0, 0, 0, 0));
	}

}
