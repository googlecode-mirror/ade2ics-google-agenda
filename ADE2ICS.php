<?php
set_time_limit(120);

/*
*	Créé par DAVIN Alexis
*	email djmemo38@gmail.com
*	Le 27/12/2011
*	
*	Version 1.7
*
*/

/* ------------- Configuration ------------- */

$vertion = "RC 1.7";

$url = "http://ade52-ujf.grenet.fr";

$projectid = "144";
if(isset($_GET['projectid']) && !empty($_GET['projectid'])){
	$projectid= htmlspecialchars($_GET["projectid"]);
}

$login = "geiirtETUDIANT";
if(isset($_GET['login']) && !empty($_GET['login'])){
	$login = htmlspecialchars($_GET["login"]);
}


$password = "etudiant";
if(isset($_GET['password'])){
	$password = htmlspecialchars($_GET["password"]);
}

/*
*  Les ressources concernées (séparés par des virgules). Une ressource peut être soit un enseignant,
*  soit un groupe d'étudiants, soit une salle. Pour connaître les codes des ressources,
*  il y a une astuce ... On ouvre ADE, et dans le cadre avec la sélection des groupes (ou ressources en général)
*  on déplie ce qu'on veut connaître et on note les numéros des ressources que l'on veut dans 
*  l'URL (resources=1613 dans l'exemple) . En faisant le test, on se rends compte que « ENEPS 1A » correspond à 1613,
*  car en passant la souris sur « ENEPS 1A », la barre de statut affiche javascript:check(1613, 'true')
*/
$resources = "1613";
if(isset($_GET['resources']) && !empty($_GET['resources'])){
	$resources = htmlspecialchars($_GET["resources"]);
}

$pattern = <<<PATTERN
#<tr><td><SPAN CLASS="value">([^>]*)</span></td><td><a href="javascript:ev\([^>]*\)">([^>]*)</a></td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td><td>([^>]*)</td></tr>#
PATTERN;

/* ---------------------------------------- */

// initialisation de la session
$ch = curl_init();

// Connection
// Configuration des options
curl_setopt($ch, CURLOPT_URL, $url."/ade/custom/modules/plannings/direct_planning.jsp?projectId={$projectid}&login={$login}&password={$password}&resources={$resources}&days=0,1,2,3,4&displayConfId=3");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);
$request=curl_exec($ch);

if(curl_errno($ch)){
    echo 'Erreur Curl : ' . curl_error($ch);
}else{
	preg_match("#error=([a-zA-Z0-9]*)#", $request, $error);
	if(empty($error[1])){
		// Transfert du cookie dans l'en-tete HTTP
		preg_match("#JSESSIONID=[a-zA-Z0-9]*#", $request, $cookie);
		curl_setopt($ch, CURLOPT_COOKIE,$cookie[0]);

		// Toute les categories
     curl_setopt($ch, CURLOPT_URL,$url."/ade/standard/gui/tree.jsp?selectId={$resources}");
		curl_exec($ch);

		// Confuguration des options du tableau
		$data = "showTabActivity=true&showTabWeek=true&showTabDay=true&showTabStage=true&showTabDate=true&showTabHour=true&aC=true&aTy=true&aUrl=true&showTabDuration=true&aSize=true&aMx=true&aSl=true&aCx=true&aCy=true&aCz=true&aTz=true&aN=true&aNe=true";
		curl_setopt($ch, CURLOPT_URL, $url."/ade/custom/modules/plannings/direct_planning.jsp?keepSelection&showTree=true");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_exec($ch);

		// Recuperations des donnees
		$semaine = array();
		for($i=0;$i<=52;$i++){
		  curl_setopt($ch, CURLOPT_URL,$url."/ade/custom/modules/plannings/info.jsp?week={$i}&reset=true&order=slot");
		  $semaine[].=curl_exec($ch);
		}
		curl_close($ch);

		// Traitement des donnees
		for ($i=0;$i<sizeof($semaine);$i++){
				preg_match_all($pattern, $semaine[$i], $resultat[]);
		}

		unset($semaine);

		for ($z=0;$z<count($resultat);$z++){
			for ($x=0;$x<count($resultat[$z]);$x++){
				for ($y=0;$y<count($resultat[$z][$x]);$y++){
					if($x!=0){
						$resultat_c[$z][$y][($x-1)] = $resultat[$z][$x][$y];
					}
				}
			}
		}

		unset($resultat);
		$resultat_c = array_merge($resultat_c);

		// Formatage date 20110906T070000Z
		$dtstart = array();
		$duree = array();
		$dtend = array();

		//Traitement de la date ex: 24/12/2011
		for ($z=0;$z<count($resultat_c);$z++){
			for ($y=0;$y<count($resultat_c[$z]);$y++){
				preg_match_all("`^(((0[1-9])|(1\d)|(2\d)|(3[0-1]))\/((0[1-9])|(1[0-2]))\/(\d{4}))`", $resultat_c[$z][$y][0], $t_date[]);
			}
		}

		//Traitement de l'heure ex: 08H00
		for ($z=0;$z<count($resultat_c);$z++){
			for ($y=0;$y<count($resultat_c[$z]);$y++){
				preg_match_all("`^(((0\d)|(1\d)|(2[0-3]))[H|h]([0-5]\d))`", $resultat_c[$z][$y][4], $t_heure[]);
			}
		}

		//Traitement de duree ex: 1h30min
		for ($z=0;$z<count($resultat_c);$z++){
			for ($y=0;$y<count($resultat_c[$z]);$y++){
				preg_match_all("`^(([0-9])h([0-5]\d))|(([0-9])[H|h])|(([0-5]\d)[MIN|min])`", $resultat_c[$z][$y][5], $t_duree[]);
			}
		}

		// Formatage date ex: 24/12/2011 -> 20111224T
		// Formatage heure ex: 08H30 -> 083000Z
		// Formatage dtstart ex: 24/12/2011 08H30 -> 20111224T083000Z
		for ($i=0;$i<count($t_date);$i++){

			$date[$i] = $t_date[$i][10][0].$t_date[$i][8][0].$t_date[$i][9][0].$t_date[$i][3][0].$t_date[$i][4][0].$t_date[$i][5][0].$t_date[$i][6][0];

			$t_h_heure[$i] = $t_heure[$i][3][0].$t_heure[$i][4][0].$t_heure[$i][5][0];
			$t_h_minute[$i] = $t_heure[$i][6][0];

			$heure[$i] = $t_h_heure[$i].$t_h_minute[$i]."00";
			$dtstart[] = $date[$i]."T".$heure[$i];	
		}

		// Formatage de duree ex: 1h30min
		for ($i=0;$i<count($t_date);$i++){

      if(($t_duree[$i][3][0].$t_duree[$i][7][0]) == NULL){
         $t_duree[$i][3][0]='00';
      }

      if(($t_duree[$i][2][0].$t_duree[$i][5][0]) == NULL){
         $t_duree[$i][2][0]='00';
      }

      if (($t_duree[$i][2][0].$t_duree[$i][5][0]) < 10 &&($t_duree[$i][2][0].$t_duree[$i][5][0]) > 0){
         $t_duree[$i][2][0] = "0".$t_duree[$i][2][0];
      }

			$t_d_heure[$i] = $t_duree[$i][2][0].$t_duree[$i][5][0];
			$t_d_minute[$i] = $t_duree[$i][3][0].$t_duree[$i][7][0];

			$t2_d_heure[$i] = $t_h_heure[$i] + $t_d_heure[$i];
			$t2_d_minute[$i] = $t_h_minute[$i] + $t_d_minute[$i];

			$t3_d_heure[$i] = ($t2_d_heure[$i] + (int)($t2_d_minute[$i]/60));
			if($t3_d_heure[$i] < 10){
				$t3_d_heure[$i] = "0".$t3_d_heure[$i];
			}

			$t3_d_minute[$i] = $t2_d_minute[$i]%60;
			if($t3_d_minute[$i] < 10){
				$t3_d_minute[$i] = "0".$t3_d_minute[$i];
			}

			$hd_duree[$i] = $t3_d_heure[$i].$t3_d_minute[$i]."00";
			$dtend[] = $date[$i]."T".$hd_duree[$i]."Z";
		}

		// Formatage des notes
		for ($i=0;$i<count($resultat_c);$i++){
			for ($n=0;$n<count($resultat_c[$i]);$n++) {
				$str     = $resultat_c[$i][$n][16];
				$order   = array("\r\n", "\n", "\r", "  ");
				$replace = array(" ", " ", " ", " ");;
				$note[] = str_replace($order, $replace, $str);
			}
		}

		// Formatage description
		$z=0;
		for ($i=0;$i<count($resultat_c);$i++){
			for ($n=0;$n<count($resultat_c[$i]);$n++) {
				$description[] = "Etudiant : ".$resultat_c[$i][$n][18];
				$description[$z] .= "\\nEnseignants : ".$resultat_c[$i][$n][19];
				$description[$z] .= "\\nDuree : ".$resultat_c[$i][$n][5];
        if($note[$z] != ''){
          $description[$z] .= "\\nNote : ".$note[$z];
        }
        $description[$z] .= "\\n\\nMise à jour le ".date("d/m/Y")." à ".date("H:i:s");
				$z++;
				
			}
		}

		// Formtage Ical
		$z=0;
		for ($i=0;$i<count($resultat_c);$i++){
			for ($n=0;$n<count($resultat_c[$i]);$n++) {
			
				// Formtage date de creation
				$created = date("Ymd")."T".date("His")."Z";

				// Formatage UID
				$UID = md5($dtstart[$z].$dtend[$z].$created);

				$ical[] = "BEGIN:VEVENT\n";
				$ical[$z] .= "DTSTART;TZID=Europe/Paris:{$dtstart[$z]}\n";
				$ical[$z] .= "DTEND;TZID=Europe/Paris:{$dtend[$z]}\n";
				$ical[$z] .= "DTSTAMP;TZID=Europe/Paris:{$dtend[$z]}\n";
				$ical[$z] .= "UID:{$UID}\n";
				$ical[$z] .= "CREATED;TZID=Europe/Paris:{$created}\n";
				$ical[$z] .= "DESCRIPTION:{$description[$z]}\n";
				$ical[$z] .= "LAST-MODIFIED;TZID=Europe/Paris:{$created}\n";
				$ical[$z] .= "LOCATION:{$resultat_c[$i][$n][20]}\n";
				$ical[$z] .= "SEQUENCE:1\n";
				$ical[$z] .= "STATUS:CONFIRMED\n";
				$ical[$z] .= "SUMMARY:{$resultat_c[$i][$n][1]}\n";
				$ical[$z] .= "TRANSP:OPAQUE\n";
				$ical[$z] .= "END:VEVENT\n";
				$z++;
			}
		}

		unset($t_date, 
		$t_heure, 
		$dtstart, 
		$duree, 
		$dtend, 
		$resultat_c, 
		$description, 
		$note, 
		$hd_duree, 
		$t_d_heure, 
		$t2_d_heure, 
		$t3_d_heure, 
		$t_d_minute, 
		$t2_d_minute, 
		$t3_d_minute, 
		$t_duree, 
		$dtstart, 
		$heure, 
		$date, 
		$t_h_heure, 
		$t_h_minute);
		
		// Creation du ICS
		header("Content-type: text/calendar; charset=iso-8859-1");
		header("Content-Disposition: attachment; filename=agenda{$created}{$resources}.ics");
		echo "BEGIN:VCALENDAR\n";
		echo "PRODID:-//DAVIN Alexis//A.Davin ADE2ICS {$vertion}//FR\n";
		echo "VERSION:{$vertion}\n";
		echo "CALSCALE:GREGORIAN\n";
		echo "METHOD:PUBLISH\n";
		echo "X-WR-CALNAME:ADE Agenda\n";
		echo "X-WR-TIMEZONE:Europe/Paris\n";
		echo "X-WR-CALDESC:Agenda ADE52 id:{$resources}\n";

		foreach ($ical as $agenda) {
			echo $agenda;
		}

		echo "END:VCALENDAR";
    unset($ical, $agenda);
	}else{
     echo "Erreur : URL invalid\n<br>URL : {$url}/ade/custom/modules/plannings/direct_planning.jsp?projectId={$projectid}&login={$login}&password={$password}&resources={$resources}&days=0,1,2,3,4&displayConfId=3";
	}
} // Fin de curl_errno
// Enjoy !!!
?>