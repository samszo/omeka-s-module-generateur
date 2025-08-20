<?php
 /*
 * @author Samuel Szoniecky
 * @category   Laminas
 * @package library\Flux\Outils
 * @license https://creativecommons.org/licenses/by-sa/2.0/fr/ CC BY-SA 2.0 FR
 * @version  $Id:$
 */
namespace Generateur\Generateur;

use Omeka\Api\Exception\RuntimeException;
use Omeka\Api\Exception\InvalidArgumentException;
use Laminas\Cache\StorageFactory;
use Laminas\Http\Client;

class Moteur {

    protected $props;
    protected $rcs;

    /**
     * @var array
     */
    var $temps = [
            1=>'indicatif présent',
            2=>'indicatif imparfait',
            3=>'passé simple',
            4=>'futur simple',
            5=>'conditionnel présent',
            6=>'subjonctif présent',
            7=>'impératif',
            8=>'participe présent',
            9=>'infinitif',
        ];
    /**
     * @var array
     */
    var $personnes = [
        1=>['lexinfo:firstPersonForm',0],        
        2=>['lexinfo:secondPersonForm',0],        
        3=>['lexinfo:thirdPersonForm',0],        
        4=>['lexinfo:firstPersonForm',1],        
        5=>['lexinfo:secondPersonForm',1],        
        6=>['lexinfo:thirdPersonForm',1]        
    ];
    /**
     * @var array
     */
    var $termType = array('a','v','m','s');
    /**
     * @var string
     */
    var $sepType = '-';
    /**
     * @var string
     */
    var $sepTerm = '_';
    /**
     * @var integer
     */
    var $maxNivReseau = 2;
    /**
     * @var array
     */
    var $data;
    /**
     * @var object
     */
    var $api;
    /**
     * Vocabularies to cache.
     *
     * @var array
     */
    protected $vocabularies = [
        'dcterms'       => 'http://purl.org/dc/terms/',
        'bibo'          => 'http://purl.org/ontology/bibo/',
        'lexinfo'       => 'http://www.lexinfo.net/ontology/3.0/lexinfo#',
        'genex'         => 'https://jardindesconnaissances.univ-paris8.fr/onto/genex#',
        'foaf'          => 'http://xmlns.com/foaf/0.1/',
        'jdc'           => 'https://jardindesconnaissances.univ-paris8.fr/onto/jdc#',
        'skos'           => 'http://www.w3.org/2004/02/skos/core#',
    ];
    /**
     * Resource template to cache.
     *
     * @var array
     */
    protected $resourcesTemplates = ["genex_Concept","genex_Conjugaison","genex_Generateur","genex_Term","genex_GenSparql"];
    /**
     * Cache of selected Omeka resource classes
     *
     * @var array
     */
    protected $resourceClasses = [];

    /**
     * Cache of selected Omeka resource template
     *
     * @var array
     */
    protected $resourceTemplate = [];

    /**
     * Cache of selected Omeka properties
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Tableau des doublons
     *
     * @var array
     */
    protected $doublons = [];
    //TODO:trouver un autre moyen
    /**
     * objet controller pour gérer : les logs
     *
     * @var object
     */
    protected $log;
    /**
     * Tableau du reseau
     *
     * @var array
     */
    protected $reseau = [];
    /**
     * Cache du module
     *
     * @var Laminas_Cache
     */
    var $cache;
    /**
     * Activation du Cache
     *
     * @var boolean
     */
    var $bCache;
    /**
     * variable de generation
     */
    var $arrFlux = array();
    var $accords = array();
    var $ordre = 0;
    var $segment = 0;
    var $niv=0;
    var $maxNiv = 20;
	var $timeDeb;
    var $timeMax = 1000;
    var $texte = "";
	var $finLigne = "<br/>";
    var $coupures=array();
    var $lang = 'fr';
	var $arrEli = array("a", "e", "é", "ê", "i","o","u","y","h");
	var $arrEliCode = array(195);
    var $arrCaract = array();
    var $defautGenre = "Mas";   

    /**
     * variable de structuration
     */
    const ENDPOINT_WIKIDATA = 'https://query.wikidata.org/sparql';
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var cnx
     */
    protected $cnx;
    /**
     * @var sql
     */
    protected $sql;

    /**
     * Construct Moteur
     *
     * @param boolean   $bCache active le cache
     * @param object    $api controller pour gérer l'api
     * @param object    $log controller pour gérer les log
     * @param object    $sql controller pour gérer les requête sql

     */
    public function __construct($bCache=true, $api, $log=false, $sql=false)
    {
        $this->api = $api;
        $this->cacheResourceClasses();
        $this->cacheResourceTemplate();
        $this->cacheProperties();
        //TODO:trouver un autre moyen de gérer le cache
        $this->bCache = false;//$bCache;
        //$this->initCache();
        $this->log = $log;
        if($sql){
            $this->sql = $sql;
            $this->cnx = $sql->getCnx();    
        }
        $this->props = [];
        $this->rcs = [];
  
    }  

    /**
     * génère à partir d'un concept
     *
     * @param array     $params paramètres de la génération
     * 
     * @return array
     */
    public function genereConcept($params)
    {
        $deb = microtime(true);
        if(isset($params["explode"])){
            $this->data = $this->sql->__invoke([
                'idConcept'=>$params["idConcept"],
                'idTerm'=>$params["idTerm"],
                'action'=>'explodeConcept']);
        }
        $this->data = $this->sql->__invoke([
            'idConcept'=>$params["idConcept"],
            'idTerm'=>$params["idTerm"],
            'action'=>'getRandomFlux']);
        if($this->data['error'])return $this->data;
        $this->arrFlux = $this->data['flux'];    
        $this->accords = $this->data['accords'];    
        $this->texte = "";
		$txtCondi = true;
		$imbCondi= false;		
        $ordreDeb = 0;
        $ordreFin = count($this->arrFlux)-1;

		for ($i = $ordreDeb; $i <= $ordreFin; $i++) {
			$this->ordre = $i;
            $this->arrFlux[$i] = $this->setAccordsFlux($this->arrFlux[$i], $i);
            $this->texte .= " ".$this->arrFlux[$i]['text'];
		}
		
        //nettoie le texte
        $this->nettoyer();

        //gestion des majuscules
        $this->genereMajuscules();

		//mise en forme du texte
		$this->coupures();

        //simplifie la structure
        $struc = [];
        foreach ($this->arrFlux as $i=>$f) {
            $struc[$i] = $this->getSimpleFluxValue($f);
        }

        return $params['structure'] ? [
            'strct'=>$struc,"times"=>["random"=>$this->data['elapsed_time'],"genere"=>$this->getDuree($deb,microtime(true))]
            ,'texte'=>$this->texte
            ] : $this->texte;
    }

    function getSimpleFluxValue($f){
        $s=[];
        foreach ($f as $k => $v) {
            if($v) {
                switch ($k) {
                    case 'cpt1gen0':
                    case 'cpt2gen0':
                        $s['gen'] = $this->getSimpleFluxValue($v);
                        break;
                    case "cpt1gen":
                    case "cpt2gen":
                        $s = array_merge($s, $this->getSimpleFluxValue($v));
                        break;
                    case 'acc':
                    case 'cpt1':
                    case 'cpt2':
                    case 'cpt1_flux':
                    case 'cpt2_flux':
                    case 'cpt1end';
                    case 'cpt2end';
                        $no = true;
                        break;
                    default:
                        $s[$k]=$v; 
                        break;
                }
            }
        }
        return $s;
    }

    function getDuree($debut,$fin){
        $elapsed = $fin - $debut;
        $minutes = floor($elapsed / 60);
        $seconds = $elapsed % 60;
        return sprintf('%d minutes and %.2f seconds', $minutes, $seconds);
    }

    /**
     * calcul les accords d'un flux
     *
     * @param array 	$flux
     * @param int 	    $num
     *
     * return array
     */
	function setAccordsFlux($flux,$num){

        //vérifie si on gère un syntagme
        if($flux["syn_id"]){
            $syn = $this->accords['syn'.$flux["syn_id"]];
            if(substr($syn['lib'],0,1)=="["){
                //calcule le générateur
                $gen = $this->genereConcept([
                    'idTerm'=>$flux["syn_id"],
                    'explode'=>true,
                    'structure'=>true
                ]);
            }else
                $flux['text'] = $syn['lib'];
            return $flux;
        }

        //vérifie si on gère une chaine de caractères
        if(!isset($flux["det_id"]) && !isset($flux["cpt1_id"]) && isset($flux["value"])){
            $flux['text'] = $flux['value'];
            return $flux;
        }

        //calcul des flux des générateurs
        if(isset($flux["cpt1_flux"])){
            //récupère la composition finale du flux
            $flux['cpt1end'] = $this->getFluxEnd($flux,"cpt1_flux");
            $flux['cpt2end'] = $this->getFluxEnd($flux,"cpt2_flux");
            foreach ($flux['cpt1end'] as $n=>$f) {
                $fluxGen = ['cpt1'=>$f];
                //ajoute l'identifiant du déterminant
                if(!isset($f['det_id']) && isset($flux['det_id'])){
                    $fluxGen['det_id'] = $flux['det_id'];
                }
                $fluxGen['det'] = $flux["det"];
                //ajoute l'identifiant du concept 2
                if(!isset($f['cpt2']) && isset($flux['cpt2end'][$n])){
                    $fluxGen['cpt2'] = $flux['cpt2end'][$n];
                }
                //vérifie si le déterminant est pour un verbe
                if(strlen($flux["det"]) > 6) $flux['cpt1gen'.$n] = $this->getFluxVerbe($flux,$num);
                else $flux['cpt1gen'.$n] = $this->getFluxAccord($fluxGen,$num+$n);    

                $flux['text'] .= $flux['cpt1gen'.$n]['text'];
                $flux['pluriel'] .= $flux['cpt1gen'.$n]["pluriel"];
                $flux['genre'] .= $flux['cpt1gen'.$n]["genre"];
            }
        }else{
            if(strlen($flux["det"]) > 6) $flux = $this->getFluxVerbe($flux,$num);
            else $flux = $this->getFluxAccord($flux,$num);
        } 

        return $flux;

    }

    /**
     * génère un flux
     *
     * @param array $flux
     * @param int $num
     *
     */
	function getFluxAccord($flux, $num){

        $flux['text'] = "";
        
        //vérifie s'il faut chercher le pluriel
        $flux['pluriel'] = isset($flux["det"]) && intval($flux["det"]) >= 50 ? true : false;

        //vérifie si les valeurs genre et nombre sont définies avant.
        if(isset($flux['det']) && substr($flux['det'],0,1) == "="){
            //récupère les valeurs
            $n = intval(substr($flux['det'],1));
            for ($i=($num-$n); $i >= 0 ; $i--) {
                if(isset($this->arrFlux[$i]['pluriel']))$flux['pluriel'] = $this->arrFlux[$i]['pluriel'];
                if(isset($this->arrFlux[$i]['genre']))$flux['genre'] = $this->arrFlux[$i]['genre'];
            }
        }

        if(isset($flux['det_id'])){
            if(isset($flux['cpt1']) && isset($flux['cpt2'])){
                //forme déterminant+adjectif+substantif = [66|a_ancien@argot_étudiant] => det_id, cpt1_flux, cpt2_flux
                //det_id donne le pluriel
                //cpt2_flux donne le genre       
                //cpt1_flux s'accorde avec det_1 et cpt2_flux
                $flux = $this->calculFluxConcept($flux, 1);
                $flux = $this->calculFluxConcept($flux, 2);
                //récupère la propriété de genre et de nombre
                $flux = $this->calculPropGenreNombre($flux,$flux['cpt2gen']);
                //calcule l'élision
                $flux['elision'] = $flux['cpt1gen']['elision'];
                //calcule le déterminant
                $flux = $this->calculFluxDeterminant($flux);
                //construction du texte
                $keyCpt1 = array_search($this->properties["genex"][$flux['propGenreNombre']]->id(), $flux['cpt1gen']['acc']['props']);
                if($keyCpt1 === false){
                    $m=__METHOD__." le concept 1 (".$flux["cpt1gen"]["term_id"].") ne possède pas un accord souhaité";
                    if($this->log)$this->log->err($m,$flux);
                    throw new RuntimeException($m);			

                }
                $keyCpt2 = array_search($this->properties["genex"][$flux['propGenreNombre']]->id(), $flux['cpt2gen']['acc']['props']);
                if($keyCpt2 === false){
                    $m=__METHOD__." le concept 2 (".$flux["cpt2gen"]["term_id"].") ne possède pas un accord souhaité";
                    if($this->log)$this->log->err($m,$flux);
                    throw new RuntimeException($m);			
                }
                $flux['text'] = $flux['detTxt']
                    .$flux['cpt1gen']['prefix']
                    .$flux['cpt1gen']['acc']['vals'][$keyCpt1]
                    ." ".$flux['cpt2gen']['prefix']
                    .($keyCpt2 ? $flux['cpt2gen']['acc']['vals'][$keyCpt2] : "");      
                return $flux;
            }
            if(isset($flux['cpt1']) && !isset($flux['cpt2'])){
                //forme déterminant+substantif = [66|argot_étudiant] => det_id, cpt1_flux
                //det_id donne le pluriel
                //cpt1_flux donne le genre et s'accorde avec det_1       
                $flux = $this->calculFluxConcept($flux, 1);
                //récupère la propriété de genre et de nombre
                $flux = $this->calculPropGenreNombre($flux,$flux['cpt1gen']);
                //calcule l'élision
                $flux['elision'] = $flux['cpt1gen']['elision'];                
                //calcule le déterminant
                $flux = $this->calculFluxDeterminant($flux);
                //construction du texte
                if(!isset($flux['cpt1gen']['acc'])){
                    $m=__METHOD__." : Impossible de trouver les accords pour le concept ".json_encode($flux);
                    if($this->log)$this->log->err($m,$flux);//cpt_id =438170 term_id =438172
                    throw new RuntimeException($m);			
                }
                $keyCpt1 = array_search($this->properties["genex"][$flux['propGenreNombre']]->id(), $flux['cpt1gen']['acc']['props']);
                $flux['text'] = $flux['detTxt']
                    .$flux['cpt1gen']['prefix']
                    .($keyCpt1 && isset($flux['cpt1gen']['acc']['vals'][$keyCpt1]) ? $flux['cpt1gen']['acc']['vals'][$keyCpt1] : "");
                return $flux;
            }
        }else{
            //forme substantif = [argot_étudiant] => cpt1_flux
            //cpt1_flux s'accorde au singulier
            $flux = $this->calculFluxConcept($flux, 1);
            $flux = $this->calculPropGenreNombre($flux,$flux['cpt1gen']);
            if(isset($flux['cpt1gen']["text"]) && $flux['cpt1gen']["text"]!=""){
                $flux['text'] .= $flux['cpt1gen']["text"];
            }else{
                $keyCpt1 = array_search($this->properties["genex"][$flux['propGenreNombre']]->id(), $flux['cpt1gen']['acc']['props']);
                $flux['text'] .= $flux['cpt1gen']['prefix']
                    .($keyCpt1 && isset($flux['cpt1gen']['acc']['vals'][$keyCpt1]) ?$flux['cpt1gen']['acc']['vals'][$keyCpt1]: "");
            }    
        }
        return $flux;

    }        

    /**
     * génère le texte d'un flux concept
     *
     * @param array $flux
     * @param array $cpt
     *
     */
    function calculFluxConcept($flux, $num){
        //vérifie si le flux est un tableau de concept
        if(isset($flux['cpt'.$num]) && is_array($flux['cpt'.$num]) ){
            //on prend le dernier concept
            $f = isset($flux['cpt'.$num]["term_id"]) ? $flux['cpt'.$num] : $flux['cpt'.$num][count($flux['cpt'.$num])-1];
        }else
            $f = $flux;
        //vérifie si la valeur est un calcul
        if($f['type']=='nombre'){
            //récupère les accords du concept
            $acc = $this->accords[$f["term_id"]][0];
            $ext = explode("->",$acc['prefix']);
            $f['text'].=mt_rand(intval($ext[0]), intval($ext[1]));
        }else{                
            if(isset($this->accords[$f["term_id"]])){
                //récupère les accords du concept
                $acc = $this->accords[$f["term_id"]][0];
                $f['acc'] = ["vals"=>explode(",",$acc['vals']),"props"=> explode(",",$acc['props'])];
                //récupère l'élision
                $kEli = array_search($this->properties["genex"]["hasElision"]->id(), $f['acc']["props"]);
                $f['elision']=$f['acc']["vals"][$kEli];
                //récupère le préfix
                $f['prefix'] = $acc['prefix'] == "0" ? "" : $acc['prefix'];  
                //récupère le genre
                $f['genre'] = isset($acc['genre']) ? ($acc["genre"] == "masculin" ? "Mas" : "Fem") : "Mas";  
            }else
                $f['text'] .= $f['value'];
        }
        $flux['cpt'.$num.'gen']=$f;
        return $flux;
    }


    function calculPropGenreNombre($flux,$f){
        /*
        if($flux['genre'] == "feminin" &&  $flux['pluriel'])$propId=$this->properties["genex"]["accordFemPlu"]->id();
        if($flux['genre'] == "feminin" &&  !$flux['pluriel'])$propId=$this->properties["genex"]["accordFemSing"]->id();
        if($flux['genre'] == "masculin" &&  $flux['pluriel'])$propId=$this->properties["genex"]["accordMasPlu"]->id();
        if($flux['genre'] == "masculin" &&  !$flux['pluriel'])$propId=$this->properties["genex"]["accordMasSing"]->id();
        */
        //ATTENTION la priorité est au flux principal pas au flux du concept
        $flux["genre"] = $flux["genre"] ? $flux["genre"] : $f["genre"];
        $flux['propGenreNombre'] ="accord";
        $flux['propGenreNombre'] .= isset($flux['genre']) ? $flux['genre'] : "Mas";                
        $flux['propGenreNombre'] .= $flux['pluriel'] ? "Plu" : "Sing";
        return $flux;
    }

    function calculFluxDeterminant($flux){
        
        //récupère les valeurs du déterminant suivant les accords
        $detTxt = "";
        foreach ($this->accords[$flux['det_id']] as $det) {
            $detProps = explode(",",$det['props']);
            $kEli = array_search($this->properties["genex"]["hasElision"]->id(), $detProps); 
            $detVals=explode(",",$det['vals']);
            if($detVals[$kEli] == $flux['elision']){
                $key = array_search($this->properties["genex"][$flux['propGenreNombre']]->id(), $detProps); 
                $flux["detTxt"] = $detVals[$key];
                $flux["detTxt"] .= $flux['elision']=="0" ? " " : "";
            }
        }
        return $flux;

    }

    /**
     * récupère le flux final d'un flux
     *
     * @param array     $flux
     * @param string    $key
     * @param int       $niv
     *
     */
	function getFluxEnd($flux,$key,$niv=0){
        if(isset($flux[$key]) && count($flux[$key])==1) return $niv ? $flux[$key][0] : [$flux[$key][0]];
        foreach ($flux[$key] as $f) {
            if($f[$key])$fluxEnd[] = $this->getFluxEnd($f,$key,$niv+1);
            elseif ($f['value'] && $f['value']!='') $fluxEnd[] = $f;
        }
        return $fluxEnd;
    }

    /**
     * génère un verbe
     *
     * @param array $flux
     * @param int   $num
     *
     */
	function getFluxVerbe($flux,$num){        
        /*
        Position 0 : type de négation
        Position 1 : temps verbal
        Position 2 : pronoms sujets définis
        Positions 3 ET 4 : pronoms compléments
        Position 5 : ordre des pronoms sujets
        Position 6 : pronoms indéfinis
        Position 7 : Place du sujet dans la chaîne grammaticale
        */


		//dans le cas d'un verbe théorique on ne fait rien
		if($flux["value"]=="v_théorique")return $flux; 
				        
		//récupère les accords du verbe
        if($flux['cpt1end']){
            $flux['cpt1end'][0]['det'] = $flux["det"];
            $accVerbe = $this->accords[$flux['cpt1end'][0]['term_id']][0];
        }else
            $accVerbe = $this->accords[$flux['cpt1_flux'][0]['term_id']][0];
        $flux['pluriel'] = $accVerbe['pluriel'];


		//récupère les pronoms
        $kDet = "detVerbe".$flux["det"][2].$flux["det"][3].$flux["det"][4].$flux["det"][6];
        $pronomVerbe = $this->accords[$kDet];
        //vérifie si le pronom sujet est génératif
        if($pronomVerbe["s"] && $pronomVerbe["s"]["lib"]=="[=1|a_il]"){
            //récupère le genre
           for ($i=$num; $i >= 0 ; $i--) {
                if(isset($this->arrFlux[$i]['genre'])){
                    $flux['genre'] = $this->arrFlux[$i]['genre'];
                    break;
                }
            }
            $flux['genre'] = isset($flux['genre']) ? $flux['genre'] : $this->defautGenre;
            $acc = $this->accords[$this->accords['a_il']][0];
            $pronom = explode(",",$acc['vals']);
            $pronomVerbe["s"]["lib"] = $flux['pluriel'] ? ($flux['genre'] == "Mas" ? $pronom[4] : $pronom[3]) : ($flux['genre'] == "Mas" ? $pronom[2] : $pronom[1]);
            $pronomVerbe["s"]["eli"] = $pronomVerbe["s"]["lib"];
        }

				
        //construction du centre
        if($accVerbe['prefix']){
            $prefix = $accVerbe['prefix'] == "0" ? "" : $accVerbe['prefix'];  
            $verbe = $prefix.$accVerbe['tms'];
        }else
            $verbe = $accVerbe['tms'];
		
			
        //construction de la forme verbale
        //gestion de l'infinitif
        if($flux["det"][1]==9 || $flux["det"][1]==7 || $flux["det"][1]==8){
            //pronom complément
            if($pronomVerbe["c"]){
                if($pronomVerbe["c"]["ident"]!=39 && $pronomVerbe["c"]["ident"]!=40 && $pronomVerbe["c"]["ident"]!=41){
                    if(!$this->isEli($verbe) || $pronomVerbe["c"]["lib"] == $pronomVerbe["c"]["eli"]){
                        $verbe = $pronomVerbe["c"]["lib"]." ".$verbe; 
                    }else{
                        $verbe = $pronomVerbe["c"]["eli"].$verbe; 
                    }
                }
            }
            //les deux parties de la négation se placent avant le verbe pour les valeurs 1, 2, 3, 4, 7 et 8
            //uniquement pour l'infinitif 
            if($flux["det"][1]==9 && in_array($flux["det"][0], [1,2,3,4,7,8])){
                $verbe = "ne ".$accVerbe['finneg']." ".$verbe;
            }else{
                if($accVerbe['finneg']!=""){
                    $verbe = $this->isEli($verbe) ? "n'".$verbe : "ne ".$verbe;
                    $verbe." ".$accVerbe['finneg'];
                }
            }
            $flux["text"] = $verbe;
        }		
        //gestion de l'ordre inverse
        if($flux["det"][5]==1){
            $verbe .= "-";
            //pronom complément
            if($pronomVerbe["c"]){
                if(!$this->isEli($verbe)){
                    $verbe = $pronomVerbe["c"]["lib"]." ".$verbe; 
                }else{
                    $verbe = $pronomVerbe["c"]["eli"].$verbe; 
                }
            }	
            $c = substr($verbe,strlen($verbe)-1);
            if(($c == "e" || $c == "a") && $accVerbe['prs']==3){
                $verbe .= "t-"; 
            }elseif($c == "e" && $accVerbe['prs']==1){
                $verbe = substr($verbe,0,-2)."é-"; 
            }
            if($this->isEli($verbe) && $accVerbe['finneg']!=""){
                $verbe = "n'".$verbe.$pronomVerbe["s"]["lib"]." ".$accVerbe["finneg"]; 
            }else{
                $verbe = "ne ".$verbe.$pronomVerbe["s"]["lib"]." ".$accVerbe["finneg"];
            }
            $flux["text"] = $verbe;
        }

        //gestion de l'ordre normal
		if(!$flux["text"]){
			$verbe .= " ".$accVerbe["finneg"];
		
            //pronom complément
			if($pronomVerbe["c"]){
				//si le pronom eli = le pronom normal on met un espace
				if(!$this->isEli($verbe) || $pronomVerbe["c"]["lib"] == $pronomVerbe["c"]["eli"]){
					$verbe = $pronomVerbe["c"]["lib"]." ".$verbe; 
				}else{
					$verbe = $pronomVerbe["c"]["eli"].$verbe; 
				}
			}	
			if($accVerbe["finneg"]){
				if($this->isEli($verbe)){
					$verbe = "n'".$verbe; 
				}else{
					$verbe = "ne ".$verbe; 
				}
			}	
            //pronom sujet
			if($pronomVerbe["s"]){
				if($this->isEli($verbe)){
					//vérification de l'apostrophe
					if (strrpos($pronomVerbe["s"]["lib"], "'") === false) { 
						$verbe = $pronomVerbe["s"]["eli"]." ".$verbe; 
					}else
                        $verbe = $pronomVerbe["s"]["eli"].$verbe; 
				}else{
					$verbe = $pronomVerbe["s"]["lib"]." ".$verbe; 
				}
			}
            //pronom indéfini
			if($pronomVerbe["i"]){
				if($this->isEli($verbe)){
					//vérification de l'apostrophe
					if (strrpos($pronomVerbe["i"]["lib"], "'") === false) { 
						$verbe = $pronomVerbe["i"]["eli"]." ".$verbe; 
					}else
                        $verbe = $pronomVerbe["i"]["eli"].$verbe; 
				}else{
					$verbe = $pronomVerbe["i"]["lib"]." ".$verbe; 
				}
			}
            $flux["text"] = $verbe;	
		}
		return $flux;

    }



    /**
     * génère à partir d'une existence du JDC
     *
     * @param array     $params paramètres de la génération
     * 
     * @return array
     */
    public function genereExiJDC($params)
    {
        $result = [];
         //récupère les rapports de génération = les rapports de l'existence ayant comme sujet l'actant générateur
        /*vérifie si la requête SQL est plus rapide que omeka        
        $temps_debut = microtime(true);
        $oRapports = $this->getRessourceRapports($exi,$params);
        $temps_fin = microtime(true);
        $tG = str_replace(".",",",round($temps_fin - $temps_debut, 4));
        */
        $temps_debut = microtime(true);
        $oRapports = $this->getRessourceRapportsSQL(['idR'=>$params['idExi'],'idSujet'=>$params['data']['id']]);
        $temps_fin = microtime(true);
        $tG1 = str_replace(".",",",round($temps_fin - $temps_debut, 4));
        //traitement des rapports de génération
        $generations=[];
        $doublons = [];
        for ($i=0; $i < count($oRapports); $i++) { 
            foreach ($params['paramsGen'] as $pg) {
                if(!isset($generations[$oRapports[$i]['o']->id()]))$generations[$oRapports[$i]['o']->id()]=[];
                switch ($pg) {
                    case 'replaceSamePredicatValue':
                        /*récupère les ressources correspondant aux prédicats = 
                        les items de la propriété Top concept dans les rapports avec :
                        propriété skos = même que l'objet
                        valeur = même que le prédicat 
                        */
                        if(!isset($doublons[$pg.$oRapports[$i]['o']->id()])){
                            $rs = $this->getRessourceWithPredicat($oRapports[$i]['o']->id());                        
                            $generations[$oRapports[$i]['o']->id()][$pg] = $rs;    
                            $doublons[$pg.$oRapports[$i]['o']->id()]=1;
                        }
                        break;                
                }
            }
        }


        //récupères les physiques de l'existence
        $exi = $this->api->read('items',$params['idExi'])->getContent();
        $physiques = $exi->value('jdc:hasPhysique',['all'=>true]);
        foreach ($physiques as $vPhy) {
          $rPhy = $vPhy->valueResource();
          //récupére les parties du physique de l'existence
          $haspart = $rPhy->value('dcterms:hasPart',['all'=>true]);
          foreach ($haspart as $p) {
            $op = json_decode($p->__toString(),true);
            //vérifie si la partie doit être générée
            if(isset($generations[$op['itemAsso']])){
                foreach ($generations[$op['itemAsso']] as $type => $data) {
                    //initialise le résultat de la génération
                    if(!isset($result[$rPhy->id()]))$result[$rPhy->id()]=[];
                    //applique le type de transformation 
                    //TODO:gérer les autres générations et les autres médias
                    switch ($type) {
                        case 'replaceSamePredicatValue':
                            $result[$rPhy->id()]=$this->replaceSamePredicatValue($result[$rPhy->id()], $rPhy, $op, $data);
                        break;
                    }
                }
            }
          }  
          $this->arrFlux[]=['jdc'=>$result];
        }        
        return ['exi'=>$exi,'rslt'=>$this->getTexte(),'data'=>$this->getData(true)];
      
    }

    /**
     * remplace la même valeur de prédicat
     *
     * @param array         $rslt = résultat de la génération
     * @param o:ressource   $r ressource omeka
     * @param array         $p partie de la ressource omeka
     * @param array         $g data pour la génération
     * 
     * @return array
     */
    function replaceSamePredicatValue($rslt, $r, $p, $g){
        if(!isset($rslt['replaceSamePredicatValue'])){
            //récupère les mots du physique
            $words = $newwords = explode(" ", trim(preg_replace('!\s+!', ' ', $r->displayTitle())));
            $rslt['replaceSamePredicatValue']=['w'=>$words,'nw'=>$newwords,'rPhy'=>$r->id(),'rTrans'=>[]];                    
        }
        for ($i=0; $i < count($p['data']); $i++) { 
            $assos = $p['data'][$i];
            if($i==0){
                //choix des données de transformation
                $nw = $g[array_rand($g)];                  
                //remplace le premier mot par la ressource choisie
                $rslt['replaceSamePredicatValue']['nw'][$assos['ordre']]=$nw['titreTopConcept'];  
            }else{
                //efface les autres mots
                $rslt['replaceSamePredicatValue']['nw'][$assos['ordre']]="";
                $nw='';
            }
            //enregistre le flux de transformation
            $rslt['replaceSamePredicatValue']['rTrans'][]=[
                'choix'=>$nw,'ordre'=>$i
                ,'src'=>$rslt['replaceSamePredicatValue']['w'][$assos['ordre']]
                ,'dst'=>$rslt['replaceSamePredicatValue']['nw'][$assos['ordre']]
            ];
        }        
        $rslt['txt']=implode(' ',$rslt['replaceSamePredicatValue']['nw']);
        
        return $rslt;

    }

    /**
     * récupère les ressources d'un jdc:rapport
     *
     * @param o:ressource   $r ressource omeka
     * @param array         $params paramètres de la génération
     * 
     * @return array
     */
    function getRessourceRapports($r, $params){
        $rapports = $r->value('jdc:hasRapport',['all'=>true]);
        $oRapports = [];
        foreach ($rapports as $r) {
          if (in_array($r->type(), ['resource', 'resource:item'])) {
            $rr=$r->valueResource();
            $rSujet = $rr->value('jdc:hasSujet')->valueResource();
            if($rSujet->id()==$params['data']['id']){
              $oRapports[]=['r'=>$rr,'s'=>$rSujet
                ,'o'=>$rr->value('jdc:hasObjet')->valueResource()
                ,'p'=>$rr->value('jdc:hasPredicat')->valueResource()];
            }
          }
        }
        return $oRapports;
      }
    /**
     * récupère les ressources d'un jdc:rapport par une requête SQL
     *
     * @param o:ressource   $r ressource omeka
     * @param array         $params paramètres de la génération
     * 
     * @return array
     */
    function getRessourceRapportsSQL($params){
        $query ="SELECT 
            r.id idExi,
            vRapport.value_resource_id idRapport,
            vSujet.value_resource_id idSujet,
            vObjet.value_resource_id idObjet,
            vPred.value_resource_id idPred
        FROM
            resource r
                INNER JOIN
            value vRapport ON vRapport.resource_id = r.id
                AND vRapport.property_id = ?
                INNER JOIN
            value vSujet ON vSujet.resource_id = vRapport.value_resource_id
                AND vSujet.property_id = ?
                INNER JOIN
            value vObjet ON vObjet.resource_id = vRapport.value_resource_id
                AND vObjet.property_id = ?
                INNER JOIN
            value vPred ON vPred.resource_id = vRapport.value_resource_id
                AND vPred.property_id = ?
        WHERE
            r.id = ?";
        $pq = [
            $this->properties["jdc"]["hasRapport"]->id()
            ,$this->properties["jdc"]["hasSujet"]->id()
            ,$this->properties["jdc"]["hasObjet"]->id()
            ,$this->properties["jdc"]["hasPredicat"]->id()
            ,$params['idR']
        ];
        if($params['idSujet']){
        $query .= " AND vSujet.value_resource_id = ? ";
        $pq[]=$params['idSujet'];
        }
        $rs = $this->cnx->fetchAll($query,$pq);
        //formate les données
        $result = [];
        foreach ($rs as $r) {
            $result[]=[
            'exi'=>$this->api->read('items',$r['idExi'])->getContent()  
            ,'rapport'=>$this->api->read('items',$r['idRapport'])->getContent()
            ,'s'=>$this->api->read('items',$r['idSujet'])->getContent()
            ,'o'=>$this->api->read('items',$r['idObjet'])->getContent()
            ,'p'=>$this->api->read('items',$r['idPred'])->getContent()
        ];
        }
        return $result;       

    }

    /**
     * récupère les ressources avec les prédicats d'une ressource
     *
     * @param int       $id identifiant de la ressource omeka
     * 
     * @return array
     */
    function getRessourceWithPredicat($id){
        $query ="SELECT 
            vRapport.id,
            vRapport.resource_id,
            p.label lblTypeRela,
            vProp.property_id idTypeRela,
            rtp.alternate_comment,
            vProp.value_resource_id idRela,
            vPropTitle.value lblRela,
            vR.value_resource_id idSemPosi,
            vTopC.value_resource_id idTopConcept,
            vTopCTitle.value titreTopConcept
        FROM
            value vRapport
                INNER JOIN
            value vProp ON vProp.resource_id = vRapport.resource_id
                INNER JOIN
            property p ON p.id = vProp.property_id
                INNER JOIN
            resource_template_property rtp ON rtp.property_id = vProp.property_id
                AND rtp.resource_template_id = ?
                AND rtp.alternate_comment IS NOT NULL
                INNER JOIN
            value vPropTitle ON vPropTitle.resource_id = vProp.value_resource_id
                AND vPropTitle.property_id = ?
                INNER JOIN
            value vR ON vR.property_id = vProp.property_id
                AND vR.value_resource_id = vProp.value_resource_id
                INNER JOIN
            value vTopC ON vTopC.property_id = ?
                AND vTopC.resource_id = vR.resource_id
                INNER JOIN
            value vTopCTitle ON vTopCTitle.resource_id = vTopC.value_resource_id
                AND vTopCTitle.property_id = ?
        WHERE
            vRapport.property_id = ?
                AND vRapport.value_resource_id = ?";
        $pq = [
        $this->api->read('resource_templates', ['label' => 'JDC Rapports entre concepts'])->getContent()->id()
        ,$this->properties["dcterms"]["title"]->id()
        ,$this->properties["skos"]["hasTopConcept"]->id()
        ,$this->properties["dcterms"]["title"]->id()
        ,$this->properties["skos"]["hasTopConcept"]->id()
        ,$id
        ];
        $rs = $this->cnx->fetchAll($query,$pq);
        /*ajoute les objets omeka
        for ($i=0; $i < count($rs); $i++) { 
            $rs[$i]['o']=$this->api->read('items',$rs[$i]['idTopConcept'])->getContent();
        }
        */
        return $rs;       

    }    

    /**
     * génère une conjugaison
     *
     * @param int       $idItem identifiant de l'item
     * @param string    $identifiant du verbe
     * 
     * @return array
     */
    public function getConjugaison($idItem, $identifiant="")
    {
        //initialise la génération 
        $this->timeDeb = microtime(true);
        $this->data = true;
        $this->arrFlux[0]["niveau"]=0;

        //récupère l'item
        $oItem = $this->api->read('items', $idItem)->getContent();

        //construction du flux génératif
        $rc = $oItem->resourceClass() ? $oItem->resourceClass()->term() : false;    
        switch ($rc) {
            case "genex:Concept":
                //récupère la description
                $desc = $oItem->value('dcterms:description');
                if(!$desc)
                    throw new RuntimeException("L'item '".$oItem->displayTitle()."' (".$oItem->id().") ne pas être conjugais.");			
                else
                    $desc = $desc->__toString();
                //génère le term
                $gen = "[01100000|".$desc."]";
                $arrFlux = $this->genGen($oItem,0,$gen)[1];
                break;                                
            case "genex:Term":
                //récupère le term
                $arrFlux = $this->setVecteurTerm($oItem,0);
                $arrFlux['item'] = $oItem;
                $arrFlux['rc'] = $rc;
                $desc = "oItem".$oItem->id();
                break;                                
            default:
                throw new RuntimeException("L'item '".$oItem->displayTitle()."' (".$oItem->id().") ne pas être conjugais.");			
                break;
        }
        $arrFlux['niveau']=0;

        //génère toutes les conjugaisons pour toutes les formes
        /*
		Position 1 : type de négation
		Position 2 : temps verbal
		Position 3 : pronoms sujets définis
		Positions 4 ET 5 : pronoms compléments
		Position 6 : ordre des pronoms sujets
		Position 7 : pronoms indéfinis
        Position 8 : Place du sujet dans la chaîne grammaticale
        */
        $result = [];
        if(!$identifiant){
            foreach ($this->temps as $tk => $t) {
                //on exclu les conjugaison thérorique
                if($tk <> 7){
                    foreach ($this->personnes as $pk => $p) {
                        $this->arrFlux=[];
                        $dv = "0".$tk.$pk."00000";
                        $arrFlux['determinant_verbe'] = $dv;
                        $this->arrFlux=[$arrFlux];                
                        $texte = $this->getTexte();
                        $this->arrFlux[0]['recid']="[".$dv."|".$desc."]";                    
                        $this->arrFlux[0]['item']=$this->arrFlux[0]['item']->id();
                        $this->arrFlux[0]['prosuj']=$this->arrFlux[0]['prosuj']->id();
                        $result[]=$this->arrFlux[0];
                        //infinitif et participe présent n'ont qu'une seule personne
                        if ($tk > 7) break;
                    }    
                }
            }
        }else{
            $this->arrFlux=[];
            $arrFlux['determinant_verbe'] = $identifiant;
            $this->arrFlux=[$arrFlux];                
            $texte = $this->getTexte();
            $this->arrFlux[0]['recid']=$this->arrFlux[0]['item']->id();                    
            $this->arrFlux[0]['item']=$this->arrFlux[0]['item']->id();
            $this->arrFlux[0]['prosuj']=$this->arrFlux[0]['prosuj']->id();
            $this->arrFlux[0]['gen']="[".$identifiant."|".$desc."]";
            $result[]=$this->arrFlux[0];
        }

        return $result;
    }


    /**
     * Structure des générateur à partir du texte et des références
     *
     * @param array             $data The data source of generate
     * 
     * @return array
     */
    public function structure($data)
    {
        $oItem = $this->api->read('items', $data['o:resource']['o:id'])->getContent();
        $this->client = new Client();                

        //traitement suivant les références
        $refs = $oItem->value('genex:hasRefWikidata',['all' => true]);        
        if($refs){
            foreach ($refs as $ref) { 
                if($ref->type()=='uri'){
                    $wikidata =  $this->getJson($ref->uri(),['format'=>'json']);
                    foreach ($wikidata['entities'] as $k => $v) {
                        //traitement suivant le type
                        switch ($v["type"]) {
                            case "lexeme":
                                # creation du term associé
                                $this->createTermFromLexeme($v, $oItem);
                                break;
                            case "item":
                                //creation d'un générateur sparql pour l'usage du concept
                                $query = 'SELECT ?item ?itemLabel ?image (MD5(CONCAT(str(?item),str(RAND()))) as ?random)
                                WHERE 
                                {
                                  ?item wdt:P31 wd:'.$v['title'].'.
                                  ?item wdt:P18 ?image.
                                  SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". }
                                }
                                ORDER BY ?random        
                                LIMIT 1';
                                $this->createGenSparql($oItem, 'usage', self::ENDPOINT_WIKIDATA, $query);
                                break;
                            }    
                    }
                }                
            }    
        }
    }

    /**
     * récupère le json d'une requête http
     *
    * @param string             $uri
     * @param array             $params
     * 
     * @return array
     */
    public function getJson($uri, $params)
    {
        $client = $this->client->setUri($uri)->setParameterGet($params);
        $response = $client->send();
        if (!$response->isSuccess()) {
            return [];
        }
        return json_decode($response->getBody(), true);
    }


    /**
     * Creation d'un générateur sparql
     *
     * @param object    $oItem item à générer
     * @param string    $titre
     * @param string    $endPoint
     * @param string    $query
     * 
     * @return o:item
     */
    public function createGenSparql($oObject, $titre, $endPoint, $query)
    {

        //vérifie la présence de l'item pour gérer la création
        $param = array();
        $param['property'][0]['property']= $this->properties["genex"]["sparqlQuery"]->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$query; 
        $result = $this->api->search('items',$param)->getContent();
        if(count($result)){
            return $result[0];
        }          

        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['genex']['Generateur']->id()];
        $oItem['o:resource_template'] = ['o:id' => $this->resourceTemplate['genex_GenSparql']->id()];
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["genex"]["sparqlEndpoint"]->id();
        $valueObject['@id'] = $endPoint;
        $valueObject['type'] = 'uri';
        $oItem[$this->properties["genex"]["sparqlEndpoint"]->term()][] = $valueObject;
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["genex"]["hasConcept"]->id();
        $valueObject['value_resource_id'] = $oObject->id();
        $valueObject['type'] = 'resource';
        $oItem[$this->properties["genex"]["hasConcept"]->term()][] = $valueObject;
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["genex"]["sparqlQuery"]->id();
        $valueObject['@value'] = $query;
        $valueObject['type'] = 'literal';
        $oItem[$this->properties["genex"]["sparqlQuery"]->term()][] = $valueObject;
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["dcterms"]["title"]->id();
        $valueObject['@value'] = uniqid('genSparql-'.$titre.'-');
        $valueObject['type'] = 'literal';
        $oItem[$this->properties["dcterms"]["title"]->term()][] = $valueObject;
       

        $result = $this->api->create('items', $oItem);        
        return $result ? $result->getContent() : false;

    }

    /**
     * Creation d'un term à partir d'un lexeme
     *
     * @param array     $lex lexeme à créer
     * @param o:item    $oItemConcept
     * 
     * @return o:item
     */
    public function createTermFromLexeme($lex, $oItemConcept)
    {

        $id = "https://www.wikidata.org/wiki/".$lex['title'];

        //vérifie la présence de l'item pour gérer la création
        $param = array();
        $param['property'][0]['property']= $this->properties["dcterms"]["isReferencedBy"]->id()."";
        $param['property'][0]['type']='eq';
        $param['property'][0]['text']=$id; 
        $result = $this->api->search('items',$param)->getContent();
        if(count($result)){
            return $result[0];
        }          

        $oItem = [];
        $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['genex']['Term']->id()];
        $oItem['o:resource_template'] = ['o:id' => $this->resourceTemplate['genex_Term']->id()];
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["dcterms"]["isReferencedBy"]->id();
        $valueObject['@id'] = $id;
        $valueObject['type'] = 'uri';
        $oItem[$this->properties["dcterms"]["isReferencedBy"]->term()][] = $valueObject;
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["genex"]["hasConcept"]->id();
        $valueObject['value_resource_id'] = $oItemConcept->id();
        $valueObject['type'] = 'resource';
        $oItem[$this->properties["genex"]["hasConcept"]->term()][] = $valueObject;
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["genex"]["hasType"]->id();
        $valueObject['@value'] = $lex['lexicalCategory'];
        $valueObject['type'] = 'literal';
        $oItem[$this->properties["genex"]["hasType"]->term()][] = $valueObject;
        foreach ($lex['lemmas'] as $l) {
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["dcterms"]["title"]->id();
            $valueObject['@value'] = $l['value'];
            $valueObject['@language']= $l['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["dcterms"]["title"]->term()][] = $valueObject;
        }


        foreach ($lex['claims'] as $k => $c) {
            switch ($k) {
                case 'P5185':
                    foreach ($c as $v) {
                        $genre = $v["mainsnak"]["datavalue"]["value"]["id"];
                        $valueObject = [];
                        $valueObject['property_id'] = $this->properties["lexinfo"]["gender"]->id();
                        $valueObject['@value'] = $genre == "Q499327" ? "1" : "2";
                        $valueObject['type'] = 'literal';
                        $oItem[$this->properties["lexinfo"]["gender"]->term()][] = $valueObject;
                    }
                break;                
                default:
                    if($this->log)$this->log->info(__METHOD__.'claims of ='.$oItemConcept->id(),$c);
                    break;
            }
        }
        //creation du tableau des valeurs pour ordonner la création : féminin en premier    
        $forms = [];
        foreach ($lex['forms'] as $form) {
            $m = intval(in_array("Q499327", $form['grammaticalFeatures']));
            $f = intval(in_array("Q1775415", $form['grammaticalFeatures']));
            $s = intval(in_array("Q110786", $form['grammaticalFeatures']));
            $p = intval(in_array("Q146786", $form['grammaticalFeatures']));
            foreach ($form['representations'] as $r) {
                $forms[$m.'-'.$f.'-'.$s.'-'.$p] = $r;
            }
        }
        if(count($forms)==2 && isset($forms['0-0-1-0'])){
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["singularNumberForm"]->id();
            $valueObject['@value'] = $forms['0-0-1-0']['value'];
            $valueObject['@language']= $forms['0-0-1-0']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["singularNumberForm"]->term()][] = $valueObject;    
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["pluralNumberForm"]->id();
            $valueObject['@value'] = $forms['0-0-0-1']['value'];
            $valueObject['@language']= $forms['0-0-0-1']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["pluralNumberForm"]->term()][] = $valueObject;                                                
        }
        if(count($forms)==2 && isset($forms['1-0-1-0'])){
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["singularNumberForm"]->id();
            $valueObject['@value'] = $forms['1-0-1-0']['value'];
            $valueObject['@language']= $forms['1-0-1-0']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["singularNumberForm"]->term()][] = $valueObject;    
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["pluralNumberForm"]->id();
            $valueObject['@value'] = $forms['1-0-0-1']['value'];
            $valueObject['@language']= $forms['1-0-0-1']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["pluralNumberForm"]->term()][] = $valueObject;                                                
        }
        if(count($forms)==4){
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["singularNumberForm"]->id();
            $valueObject['@value'] = $forms['0-1-1-0']['value'];
            $valueObject['@language']= $forms['0-1-1-0']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["singularNumberForm"]->term()][] = $valueObject;    
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["singularNumberForm"]->id();
            $valueObject['@value'] = $forms['1-0-1-0']['value'];
            $valueObject['@language']= $forms['1-0-1-0']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["singularNumberForm"]->term()][] = $valueObject;    
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["pluralNumberForm"]->id();
            $valueObject['@value'] = $forms['0-1-0-1']['value'];
            $valueObject['@language']= $forms['0-1-0-1']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["pluralNumberForm"]->term()][] = $valueObject;                                                
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["lexinfo"]["pluralNumberForm"]->id();
            $valueObject['@value'] = $forms['1-0-0-1']['value'];
            $valueObject['@language']= $forms['1-0-0-1']['language'];
            $valueObject['type'] = 'literal';
            $oItem[$this->properties["lexinfo"]["pluralNumberForm"]->term()][] = $valueObject;                                                
        }

        foreach ($lex['senses'] as $s) {
            foreach ($s['glosses'] as $g) {
                $valueObject = [];
                $valueObject['property_id'] = $this->properties["dcterms"]["description"]->id();
                $valueObject['@value'] = $g['value'];
                $valueObject['@language']= $g['language'];
                $valueObject['type'] = 'literal';
                $oItem[$this->properties["dcterms"]["description"]->term()][] = $valueObject;                                                
            }
        }            

        $result = $this->api->create('items', $oItem);        
        return $result ? $result->getContent() : false;

    }

    /**
     * initialise la génération
     *
     */
    public function initGenerate($data)
    {   
        $this->data = $data;
        $this->arrFlux = array();
        $this->ordre = 0;
        $this->arrFlux[$this->ordre]["niveau"]=0;        
        $this->segment = 0;    
        $this->timeDeb = microtime(true);
    }


    /**
     * The generate with item and determiannt
     *
     * @param array     $data The data source of generate
     * 
     * @return array
     */
    public function generateDeterminantItem($data)
    {   
        $this->initGenerate($data);
        $oItem = $this->api->read('items', $this->data['o:resource']['o:id'])->getContent();
        $this->genGen($oItem,0,$this->data['gen']);
        $this->getTexte();
    }


    /**
     * The generate with data
     *
     * @param array     $data The data source of generate
     * @param o:item    $oItem item à générer
     * @param integer   $niveau profondeur de la génération
     * 
     * @return array
     */
    public function generate($data, $oItem=false, $niveau=0)
    {   
        $first = false;
        if(!isset($this->data)){
            $first = true;
            $this->initGenerate($data);
            $oItem = $this->api->read('items', $this->data['o:resource']['o:id'])->getContent();
        }
        if($this->log)$this->log->info(__METHOD__.' item id ='.$oItem->id());
        $rc = $oItem->resourceClass() ? $oItem->resourceClass()->term() : false;    

        $this->arrFlux[$this->ordre]["niveau"] = $niveau;
        $this->arrFlux[$this->ordre]["item"] = $oItem;
        $this->arrFlux[$this->ordre]["rc"] = $rc;

        //generates according to class
        switch ($rc) {
            case "genex:GenSparql":
                $this->genSparql($oItem, $niveau);
            case "genex:Generateur":
                //if($first)$this->ordre ++;                
                $this->genGen($oItem, $niveau);
                break;            
            case "genex:Concept":
                //if($first)
                $this->ordre ++;
                $this->genConcept($oItem,true,$niveau);
                break;                                
            case "genex:Term":
                $this->setVecteurTerm($oItem,$niveau);
                break;                                
            default:
                //si class inconnu on ne fait rien
                break;
        }

        if($first) $this->getTexte();

        return $this->arrFlux;
    }

    /**
     * génère le texte à partir du flux sélectionné
     *
     * @return string
     */
    public function getTexte()
    {


		$this->texte = "";
		$txtCondi = true;
		$imbCondi= false;		
        $ordreDeb = 0;
        $ordreFin = count($this->arrFlux)-1;
		
		for ($i = $ordreDeb; $i <= $ordreFin; $i++) {
			$this->ordre = $i;
			$texte = "";
			if(isset($this->arrFlux[$i])){
				$arr = $this->arrFlux[$i];
				if(isset($arr["txt"])){					                    
                    $this->texte .= str_replace("%",$this->finLigne,$arr["txt"]);
				}elseif(isset($arr["ERREUR"]) && $this->showErr){
					$this->texte .= '<ul><font color="#de1f1f" >ERREUR:'.$arr["ERREUR"].'</font></ul>';	
				}elseif(isset($arr["item"])){	
                    $this->arrFlux[$i]=$this->genereItem($arr);				
                    $this->texte .= $this->arrFlux[$i]["txt"]." ";
				}elseif(isset($arr["jdc"])){
                    foreach ($arr["jdc"] as $k => $v) {
                        $this->texte .= $v["txt"]." ";
                    }	
                }
                    
			}
		}
		
        //nettoie le texte
        $this->nettoyer();

        //gestion des majuscules
        $this->genereMajuscules();

		//mise en forme du texte
		$this->coupures();

        return $this->texte;

    }

    /**
     * Fonction pour nettoyer le texte
     *
     *
     */
	function nettoyer(){

        $this->texte = str_replace("<","",$this->texte);        
        $this->texte = str_replace(">","",$this->texte);        

        //gestion des espace en trop
		//$this->texte = preg_replace('/\s\s+/', ' ', $this->texte); //problème avec à qui devient illisible ???
		$this->texte = str_replace("  "," ",$this->texte);
		$this->texte = str_replace("  "," ",$this->texte);
		$this->texte = str_replace("  "," ",$this->texte);
		$this->texte = str_replace("  "," ",$this->texte);
		$this->texte = str_replace("  "," ",$this->texte);
		$this->texte = str_replace("  "," ",$this->texte);
        
		$this->texte = str_replace("&#039; ","&#039;",$this->texte);
         
		$this->texte = str_replace("’ ","’",$this->texte);
		$this->texte = str_replace("' ","'",$this->texte);
		$this->texte = str_replace(" , ",", ",$this->texte);
		$this->texte = str_replace(" .",".",$this->texte);
		$this->texte = str_replace(" 's","'s",$this->texte);
		$this->texte = str_replace(" -","-",$this->texte);
		$this->texte = str_replace("- ","-",$this->texte);
		$this->texte = str_replace("( ","(",$this->texte);
        $this->texte = str_replace(" )",")",$this->texte);

    }


    /**
     * Fonction pour couper le texte
     *
     *
     */
	function coupures(){
		//mise en forme du texte
		/*coupure de phrase*/
		if(count($this->coupures)==2){
			$this->texte .= " ";
			$LT = strlen($this->texte);
			$nbCaractCoupure = mt_rand($this->coupures[0], $this->coupures[1]);
			$i = $nbCaractCoupure;
			while(($i+$this->coupures[1]) < $LT) {
				//trouve la coupure
				$c = substr($this->texte, $i, 1);
				$this->trace($nbCaractCoupure." ".$c." ".$i."/".$LT);
				$go = true;
				$j = $i;
				while ($go) {
					if($c == "" || $c == " " || $c == "," || $c == "." || $c == ";"){
						$go=false;
						$i = $j;
					}elseif ($j==0){
						//coupe jusqu'au prochain espace
						$i = strpos($this->texte, ' ', $i);
						$go=false;
					}else{
						$j --;
						$c = substr($this->texte, $j, 1);
					}
				}
				$this->texte = trim(substr($this->texte, 0, $i)).$this->finLigne.trim(substr($this->texte, $i));
				$nbCaractCoupure = mt_rand($this->coupures[0], $this->coupures[1]);
				$i += $nbCaractCoupure+strlen($this->finLigne);
				$LT = strlen($this->texte);
			}
			
		}
	}

    /**
     * génère un les majuscule
     * return string with first letters of sentences capitalized
     * merci à https://www.php.net/manual/fr/function.ucfirst.php#120689
     * @param array $arr
     *
     */
    function genereMajuscules() {
        $str = $this->texte;
        if ($str) { // input
        $str = preg_replace('/'.chr(32).chr(32).'+/', chr(32), $str); // recursively replaces all double spaces with a space
        if (($x = substr($str, 0, 10)) && ($x == strtoupper($x))) $str = strtolower($str); // sample of first 10 chars is ALLCAPS so convert $str to lowercase; if always done then any proper capitals would be lost
        $na = array('. ', '! ', '? '); // punctuation needles
        foreach ($na as $n) { // each punctuation needle
            if (strpos($str, $n) !== false) { // punctuation needle found
            $sa = explode($n, $str); // split
            foreach ($sa as $s) $ca[] = ucfirst($s); // capitalize
            $str = implode($n, $ca); // replace $str with rebuilt version
            unset($ca); //  clear for next loop
            }
        }
        $this->texte = ucfirst(trim($str)); // capitalize first letter in case no punctuation needles found
        }
    }


    /**
     * génère un verbe
     *
     * @param array $arr
     *
     */
	function genereVerbe($arr){

		/*
		Position 1 : type de négation
		Position 2 : temps verbal
		Position 3 : pronoms sujets définis
		Positions 4 ET 5 : pronoms compléments
		Position 6 : ordre des pronoms sujets
		Position 7 : pronoms indéfinis
		Position 8 : Place du sujet dans la chaîne grammaticale
		*/
		$arr["debneg"]="";
		$arr["finneg"]="";

		//dans le cas d'un verbe théorique on ne fait rien
		if(isset($arr["class"]) && $arr["class"]=="v_théorique"){
            $arr['verbe']="";
            return $arr;    
        }
				
		//génère le pronom
		$arr = $this->getPronom($arr);    	
		
		//récupère la terminaison
		$arr = $this->genereTerminaison($arr);
		
        //construction du centre
        $prefix = "";
        if($arr['prefixConj']){
            $vPrefix = $arr['item']->value('genex:hasPrefix',['lang' => $this->lang,'all'=>true]);
            if(!$vPrefix || !is_array($vPrefix)){
                throw new InvalidArgumentException("Impossible de générer le verbe '".$arr['item']->displayTitle()."' (".$arr['item']->id().") pas de prefix défini.");			
            }elseif(count($vPrefix)==2) $prefix = $vPrefix[1]->__toString();            
            else $prefix = $vPrefix[0]->__toString();
            $prefix = $prefix == "0" ? "" : $prefix;  
            $centre = $prefix.$arr['terminaison'];
        }else
            $centre = $arr['terminaison'];
        $arr['prefix']=$prefix;
		
		//construction de l'élision
		$eli = $this->isEli($centre);
		
        $verbe="";

        if(isset($arr['prodem'])){
            $prodem['lib'] = $arr['prodem']->value('genex:hasPrefix') ? $arr['prodem']->value('genex:hasPrefix',['lang' => $this->lang])->__toString() : "";
            $prodem['lib_eli'] = $arr['prodem']->value('genex:hasElision') ? $arr['prodem']->value('genex:hasElision',['lang' => $this->lang])->__toString() : "";
            $prodem['num'] = $arr['prodem']->value('dcterms:description') ? $arr['prodem']->value('dcterms:description',['lang' => $this->lang])->__toString() : "";
            $prodem['num'] = str_replace('complement','',$prodem['num']);
        }


		if(isset($arr["determinant_verbe"])){
			//génère la négation
			if($arr["determinant_verbe"][0]!=0){
                $arr["finneg"] = $this->getGeneClass("Negation","Negation".$arr["determinant_verbe"][0])->displayTitle();
				if($eli==0){
					$arr["debneg"] = "ne ";	
				}else{
					if(isset($arr["prodem"]) && !$this->isEli($prodem["lib"])){
						$arr["debneg"] = "ne ";
					}else{
						$arr["debneg"] = "n'";						
					}
				}
			}
			
			//construction de la forme verbale
			/*gestion de l'infinitif 
			 * 
			 */
			if($arr["determinant_verbe"][1]==9 || $arr["determinant_verbe"][1]==7 || $arr["determinant_verbe"][1]==8){
				$verbe = $centre;
				if(isset($arr["prodem"])){
					if($prodem["num"]!=39 && $prodem["num"]!=40 && $prodem["num"]!=41){
						if(!$this->isEli($verbe) || $prodem["lib"] == $prodem["lib_eli"]){
							$verbe = $prodem["lib"]." ".$verbe; 
						}else{
							$verbe = $prodem["lib_eli"].$verbe; 
							$eli=0;
						}
					}
				}
				//les deux parties de la négation se placent avant le verbe pour les valeurs 1,2, 3, 4, 7 et 8
				//uniquement pour l'infinitif 
				if($arr["determinant_verbe"][1]==9 && (
					$arr["determinant_verbe"][0]==1 || $arr["determinant_verbe"][0]==2 
					|| $arr["determinant_verbe"][0]==3 
					|| $arr["determinant_verbe"][0]==4 
					|| $arr["determinant_verbe"][0]==7 
					|| $arr["determinant_verbe"][0]==8)){
					$verbe = "ne ".$arr["finneg"]." ".$verbe;
				}else{
					if($this->isEli($verbe) && $arr["finneg"]!=""){
						$verbe = "n'".$verbe." ".$arr["finneg"];
					}else{
						$verbe = $arr["debneg"].$verbe." ".$arr["finneg"];
					}
				}
				if(isset($arr["prodem"])){
					//le pronom complément se place en tête lorsqu’il a les valeurs 39, 40, 41
	                if($prodem["num"]==39 || $prodem["num"]==40 || $prodem["num"]==41){
						if(!$this->isEli($verbe) || $prodem["lib"] == $prodem["lib_eli"]){
							$verbe = $prodem["lib"]." ".$verbe; 
						}else{
							$verbe = $prodem["lib_eli"].$verbe; 
							$eli=0;
						}
					}
				}								
			}		
			//gestion de l'ordre inverse
			if($arr["determinant_verbe"][5]==1){
				$verbe = $centre."-";
				if(isset($arr["prodem"])){
					if(!$this->isEli($verbe)){
						$verbe = $prodem["lib"]." ".$verbe; 
					}else{
						$verbe = $prodem["lib_eli"].$verbe; 
					}
				}	
				$c = substr($centre,strlen($centre)-1);
				if(($c == "e" || $c == "a") && $arr["terminaison"]==3){
					$verbe .= "t-"; 
				}elseif($c == "e" && $arr["terminaison"]==1){
					$verbe = substr($verbe,0,-2)."é-"; 
				}
				if($this->isEli($verbe) && $arr["debneg"]!=""){
					$verbe = "n'".$verbe.$arr['prosujlib']." ".$arr["finneg"]; 
				}else{
					$verbe = $arr["debneg"]." ".$verbe.$arr['prosujlib']." ".$arr["finneg"];
				}
			}
		}
		//gestion de l'ordre normal
		if($verbe==""){
			$verbe = $centre." ".$arr["finneg"];
			if(isset($arr["prodem"])){
				//si le pronom eli = le pronom normal on met un espace
				if(!$this->isEli($verbe) || $prodem["lib"] == $prodem["lib_eli"]){
					$verbe = $prodem["lib"]." ".$verbe; 
				}else{
					$verbe = $prodem["lib_eli"].$verbe; 
					$eli=0;
				}
			}	
			if($arr["debneg"]!=""){
				if($this->isEli($verbe)){
					$verbe = "n'".$verbe; 
				}else{
					$verbe = $arr["debneg"].$verbe; 
				}
			}	
			if($arr["prosuj"]!=""){
                $lib = $arr['prosuj']->value('genex:hasPrefix') ? $arr['prosuj']->value('genex:hasPrefix',['lang' => $this->lang])->__toString() : "";
                $lib_eli = $arr['prosuj']->value('genex:hasElision') ? $arr['prosuj']->value('genex:hasElision',['lang' => $this->lang])->__toString() : "";
				if($this->isEli($verbe)){
					//vérification de l'apostrophe
					if (strrpos($lib_eli, "'") === false) { 
						$verbe = $lib_eli." ".$verbe; 
					}else
                        $verbe = $lib_eli.$verbe; 
                    $arr['sujet']=$lib_eli;
				}else{
					$verbe = $lib." ".$verbe; 
                    $arr['sujet']=$lib_eli;
				}
			}	
		}
		$arr['verbe']=$verbe;
		return $arr;

    }

	function isEli($s){
		if(in_array($s[0], $this->arrEli) || in_array(ord($s), $this->arrEliCode))return true;
		else return false;
	}

    /**
     * Génére la terminaison d'un determinant de verbe
     *
     * @param array $arr
     *
     */
	function genereTerminaison($arr){
		//par défaut la terminaison est 3eme personne du singulier
        $p = $this->personnes[3];

		if(isset($arr["determinant_verbe"])){
            $arr['temps'] = $this->temps[$arr["determinant_verbe"][1]];
            
            //formule de calcul de la terminaison suivant persones + nombre            
			if($arr["determinant_verbe"][1]==8 || $arr["determinant_verbe"][1]==9){
                $p = $this->personnes[1];
            }elseif(isset($arr['terminaison'])) $p = $this->personnes[$arr['terminaison']];
        }
        //met à jour le vecteur pluriel
        $arr['vecteur']['pluriel']=$p[1];

        //récupère la conjugaison	        
        $oConj = $this->getIdConjugaison($arr);

        //vérifie si la conjugaison nécessite un prefix
        $arr['prefixConj'] = $oConj->value('genex:hasPrefix') ? boolval($oConj->value('genex:hasPrefix')->asHtml()) : true;
            
		//gestion des terminaisons ---
		$txt = $this->getTerminaison($oConj->id(),$arr['temps'],$p[0],$p[1]);
		if($txt=="---" || $txt=="- ")$txt="";		
        $arr['terminaison']=$txt;        

        return $arr;
    }		

    /**
     * Récupère la conjugaison d'un verbe
     *
     * @param array     $arr
     * @param boolean   $bNull
     *
     * @return o:item;
     */
	function getIdConjugaison($arr, $bNull=true){

        $conj = $arr['item']->value('genex:hasConjugaison',['all'=>true]);
        if(!$conj && !$bNull)
            throw new InvalidArgumentException("La conjugaison pour '".$arr['item']->displayTitle()."' (".$arr['item']->id().") n'a pas été trouvée.");			
        if(!$conj && $bNull)
            return false;			

        $idConj = false;
        foreach ($conj as $c) {
            if($c->type()=='resource'){
                $oConj = $c->valueResource();
                $idConj = $oConj->id();                    
            }                
        }
        if(!$idConj && !$bNull)
            throw new InvalidArgumentException("La conjugaison pour '".$arr['item']->displayTitle()."' (".$arr['item']->id().") n'a pas été trouvée.");			
        
        return $oConj;

    }
    

    /**
     * Récupère la terminaison d'une conjugaison
     *
     * @param interger $idConj
     * @param interger $temps
     * @param interger $personne
     * @param integer  $nombre
     *
     * @return string;
     */
	function getTerminaison($idConj, $temps, $personne, $nombre){

        $c = "getTerminaison".$idConj.md5($temps);
        $success = false;        
        if($this->bCache)
            $id = $this->cache->getItem($c, $success);

        if (!$success) {
            $query = [
                'resource_class_id'=>[$this->resourceClasses['genex']['ConjugaisonTempsTerms']->id()],
                'property'=>[
                    ['joiner'=>'and','property'=>$this->properties['lexinfo']['mood']->id(),'type'=>'eq','text'=>$temps],
                    ['joiner'=>'and','property'=>$this->properties['genex']['hasConjugaison']->id(),'type'=>'res','text'=>$idConj]
                ]
            ];
            $items = $this->api->search('items',$query,['limit'=>0])->getContent();
            
            if(count($items)==0)
                throw new RuntimeException("Impossible de récupérer la conjugaison. Aucun '".$idConj." ".$temps."' n'a été trouvés");
            if(count($items)>1)
                throw new RuntimeException("Impossible de récupérer la conjugaison. Plusieurs items '".$idConj." ".$temps."' ont été trouvés");

            $oItem = $items[0];
            $this->cache->setItem($c, $oItem->id());
        }else
            $oItem = $this->api->read('items', $id)->getContent();

        $term = $oItem->value($personne,['lang' => $this->lang, 'all'=>true])[$nombre];
        if(!$term)
            throw new RuntimeException("Impossible de récupérer la terminaison ".$personne." ".$nombre." pour l'item '".$oItem->displayTitle()." : (".$oItem->id().").");
        else
            $term = $term->__toString();

        return $term == "---" ? "" : $term;            

    }

    /**
     * Génére le pronom d'un determinant de verbe
     *
     * @param array $arr
     *
     */
	function getPronom($arr){
		
		//par défaut la terminaison = 3
		$arr["terminaison"] = 3;
		
		//vérifie la présence d'un d&terminant
		if(isset($arr["determinant_verbe"])){
			if($arr["determinant_verbe"][6]!=0){
				//pronom indéfinie
				$arr["prosuj"] = $this->getGeneClass("Pronom","sujet_indefini".$arr["determinant_verbe"][6]);
			}elseif($arr["determinant_verbe"][2]==0){
				//pas de pronom
				$arr["prosuj"] = "";
			}
			
			//pronom définie
			$numP = $arr["determinant_verbe"][2];
			$pr = "";
            //définition des terminaisons et du pluriel
			if($numP==7){
                //TODO : vérifier les accords en genre et nombre
                $arr["terminaison"] = 6;				
				//il/elle singulier pluriel
                $pr = $this->getItemByClass("a_il");
                $arr["prosuj"] = $this->genConcept($pr,false);
                //récupère le pronom suivant le genre et le nombre
                $vecteur = $this->getVecteur('genre');
                $p = $numP==6 ? 'lexinfo:pluralNumberForm' : 'lexinfo:singularNumberForm';
                $lib = $arr['prosuj']->value($p,['all'=>true]);
                $arr["prosujlib"] = $lib[$vecteur['genre']-1]->__toString();        
			}elseif($numP==0){
                //3eme personne du singulier
				$arr["terminaison"] = 3;				
            }elseif($numP==8){
                //pas de pronom singulier
                $arr["prosuj"] = "";
				$arr["terminaison"] = 3;				
			}elseif($numP==9){
				//pas de pronom pluriel
                $arr["prosuj"] = "";
				$arr["terminaison"] = 6;				
			}else{
				$arr["terminaison"] = $numP;				
                $arr["prosuj"] = $this->getGeneClass("Pronom","sujet".$numP);
			}

			//pronom complément
			if($arr["determinant_verbe"][3]!=0 || $arr["determinant_verbe"][4]!=0){
				$numPC = $arr["determinant_verbe"][3].$arr["determinant_verbe"][4];
                $arr["prodem"] = $this->getGeneClass("Pronom","complement".$numPC);
                if(isset($arr["vecteur"]['elision']))
                    $prodem = $arr["prodem"]->value('genex:hasElision',['lang' => $this->lang])->__toString();
                else
                    $prodem = $arr["prodem"]->value('genex:hasPrefix',['lang' => $this->lang])->__toString();
		        if(substr($prodem,0,1)== "["){
                    $pr = $this->getItemByClass($prodem);
                    $arr["prodem"] = $this->genConcept($pr,false);               
                }
			}			
		}		
		return $arr;
	}


    /**
     * Récupère un item correspondant à une class genex 
     *
     * @param string $class
     * @param string $desc
     *
     * @return o:Item
     */
	public function getGeneClass($class, $desc){

        $c = "getGeneClass".md5($class).md5($desc);
        $success = false;        
        if($this->bCache)
            $id = $this->cache->getItem($c, $success);

        if (!$success) {
            $query = [
                'resource_class_id'=>[$this->resourceClasses['genex'][$class]->id()],
                'property'=>[
                    ['joiner'=>'and','property'=>$this->properties['dcterms']['description']->id(),'type'=>'eq','text'=>$desc]
                ]
            ];
            $items = $this->api->search('items',$query,['limit'=>0])->getContent();
            
            if(count($items)==0)
                throw new RuntimeException("Impossible de récupérer la class. Aucun '".$class." : ".$desc."' n'a été trouvés");
            if(count($items)>1)
                throw new RuntimeException("Impossible de récupérer la class. Plusieurs items '".$class." : ".$desc."' ont été trouvés");

            $oItem = $items[0];
            $this->cache->setItem($c, $oItem->id());
        }else
            $oItem = $this->api->read('items', $id)->getContent();

        return $oItem;        

    }
 

    /**
     * génère un flux determinant
     *
     * @param array $arr
     *
     */
	function genereDeterminant($arr){

        //calcul le déterminant
        if(!isset($arr['determinant']) || is_bool($arr['determinant'])){
            $arr['txtDeterminant'] = "";
            return $arr;
        }
        $det = "";
        $vecteur = $arr['vecteur'];
        //positionne les valeur par défaut
        if(!isset($vecteur["elision"]))$vecteur["elision"]="0";
        if(!isset($vecteur["genre"]))$vecteur["genre"]=$this->defautGenre;
        $oItem = $arr['determinant'];
		if($vecteur){			
            if($this->log)$this->log->info(__METHOD__.' '.$oItem->id(),$vecteur);
            if($vecteur['pluriel'])
                $formDet = $oItem->value('lexinfo:pluralNumberForm',['lang' => $this->lang, 'all'=>true]);
            else
                $formDet = $oItem->value('lexinfo:singularNumberForm',['lang' => $this->lang, 'all'=>true]);

            if($vecteur["elision"]=="0" && $vecteur["genre"]=="1"){
				$det = $formDet[1]->__toString()." ";
			}
			if($vecteur["elision"]=="0" && $vecteur["genre"]=="2"){
				$det = $formDet[0]->__toString()." ";
			}
			if($vecteur["elision"]=="1" && $vecteur["genre"]=="1"){
				$det = $formDet[3]->__toString()." ";
			}
			if($vecteur["elision"]=="1" && $vecteur["genre"]=="2"){
				$det = $formDet[2]->__toString()." ";
			}
		}
        $arr['txtDeterminant'] = $det;		
		return $arr;
	}


    
    

    /**
     * génére un flux item
     *
     * @param array $item
     * 
     * @return array
     *
     */
	public function genereItem($item){

        $oItem = $item['item'];
        $txt = "";
        if($this->log)$this->log->info(__METHOD__.' '.$oItem->id());
        //generates according to class
        switch ($item['rc']) {
            case "genex:Generateur":
                if(isset($item["determinant"])){
                    $item = $this->genereDeterminant($item);
                    $txt = $item['txtDeterminant'];    
                }
                break;                                
            case "genex:Concept":
                if(isset($item["determinant"])){
                    $item = $this->genereDeterminant($item);
                    $txt = $item['txtDeterminant'];    
                }
                break;                                
            case "genex:Term":
                $prefix = $oItem->value('genex:hasPrefix',['lang' => $this->lang]) ? $oItem->value('genex:hasPrefix',['lang' => $this->lang])->__toString() : "";
                //gestion des chaine vide impossible dans omeka s
                if($prefix=='---')$prefix='';
                $txt=$prefix;
                if(isset($item['determinant_verbe'])){
                        $item = $this->genereVerbe($item);                        
                        $txt = $item['verbe'];
                }elseif(isset($item["vecteur"])){
                        $item = $this->genereDeterminant($item);
                        $term = "";
                        //récupère la terminaison du terme suivant le nombre
                        $pluriel = isset($item["vecteur"]['pluriel']) ? $item["vecteur"]['pluriel'] : false;
                        $term = $pluriel ? 
                            $oItem->value('lexinfo:pluralNumberForm',['lang' => $this->lang, 'all' => true]) 
                            : $oItem->value('lexinfo:singularNumberForm',['lang' => $this->lang, 'all' => true]);
                        if(is_array($term) && count($term)==2){
                            $term = isset($item["vecteur"]['genre'])
                                && $item["vecteur"]['genre']=="2" ? $term[0]->__toString() : $term[1]->__toString();
                        }else
                            $term = $term ? $term[0]->__toString() : "";

                        //si la terminaison est vide 
                        if($term==""){
                            //vérifie si le term à une conjugaison
                            $oConj = $this->getIdConjugaison($item, true);
                            if($oConj){
                                if(!isset($item['txtDeterminant'])){
                                    //on prend l'infinitif
                                    $term = $this->getTerminaison($oConj->id(),'infinitif',$this->personnes[1][0],$this->personnes[1][1]);
                                }else{
                                    //on prend la troisième personne du singulier au présent de l'indicatif
                                    $term = $this->getTerminaison($oConj->id(),'indicatif présent',$this->personnes[3][0],$this->personnes[3][1]);
                                }                                
                                $hasprefix = $oConj->value('genex:hasPrefix') ? boolval($oConj->value('genex:hasPrefix')->__toString()) : true;
                                $prefix = $hasprefix ? $prefix : "";    
                            }   
                        }
    
                        //construction du texte
                        $txt = $item['txtDeterminant'].$prefix.$term;    
                }
                break;                                
            default:
                //si class inconnu on ne fait rien
                break;
        }
		$item['txt'] = $txt == "---" ? "" : $txt;		
		return $item;
	}

    /**
     * met à jour le vecteur d'un term
     *
     * @param o:Item 	$oItem
     * @param integer   $niveau profondeur de la génération
     *
     * return array
     */
	function setVecteurTerm($oItem, $niveau){

        //si le term n'a pas de genre ni de forme pluriel et singulier ni de conjugaison
        // = syntagme => il n'y a pas de vecteur
        if(!$oItem->value('lexinfo:gender') 
            && !$oItem->value('lexinfo:pluralNumberForm') 
            && !$oItem->value('lexinfo:pluralNumberForm')
            && !$oItem->value('genex:hasConjugaison')) return;

        //si le term a un déterminant de verbe : pas de vecteur
        //if(isset($this->arrFlux[$this->ordre]["determinant_verbe"])) return;
        
        //ajoute les vecteurs
        $elision = $oItem->value('genex:hasElision',['lang' => $this->lang]) ? $oItem->value('genex:hasElision',['lang' => $this->lang])->asHtml() : "0";//pas d'élision par défaut
        $genre = $oItem->value('lexinfo:gender',['lang' => $this->lang]) ? $oItem->value('lexinfo:gender',['lang' => $this->lang])->asHtml() : $this->defautGenre;//masculin par défaut
        if(!isset($this->arrFlux[$this->ordre]["vecteur"])){
            $this->arrFlux[$this->ordre]["vecteur"]["elision"] = $elision;    
            $this->arrFlux[$this->ordre]["vecteur"]["genre"] = $genre;
        }else{
            if(!isset($this->arrFlux[$this->ordre]["vecteur"]["elision"])){
                $this->arrFlux[$this->ordre]["vecteur"]["elision"] = $elision;    
            }
            if(!isset($this->arrFlux[$this->ordre]["vecteur"]["genre"])){
                $this->arrFlux[$this->ordre]["vecteur"]["genre"] = $genre;
            }                    
        }

        //vérifie s'il faut ajouter gérer les vecteurs et les déterminants des niveaux précédents
        if($niveau > 0){
            $ordreNiveauBase = $this->ordre-$niveau+1;
            if(isset($this->arrFlux[$ordreNiveauBase]["vecteur"])){
                $this->arrFlux[$ordreNiveauBase]["vecteur"]["elision"] = $elision;                    
                $this->arrFlux[$this->ordre]["vecteur"]["pluriel"] = isset($this->arrFlux[$ordreNiveauBase]["vecteur"]["pluriel"]) ? $this->arrFlux[$ordreNiveauBase]["vecteur"]["pluriel"] : false;    
                //si le genre est déjà défini par '=1' on le récupère
                if(isset($this->arrFlux[$ordreNiveauBase]["vecteur"]["genre"]))$this->arrFlux[$this->ordre]["vecteur"]["genre"]=$this->arrFlux[$ordreNiveauBase]["vecteur"]["genre"];
                else $this->arrFlux[$ordreNiveauBase]["vecteur"]["genre"]=$genre;
            }
            if(isset($this->arrFlux[$ordreNiveauBase]["determinant"])){
                $this->arrFlux[$this->ordre]["determinant"] = $this->arrFlux[$ordreNiveauBase]["determinant"];    
            }
            if(isset($this->arrFlux[$ordreNiveauBase]["determinant_verbe"])){
                $this->arrFlux[$this->ordre]["determinant_verbe"] = $this->arrFlux[$ordreNiveauBase]["determinant_verbe"];    
                //récupère le premier vecteur précédent
                for($i = $this->ordre-1; $i >= 0; $i--){
                    if(isset($this->arrFlux[$i]["vecteur"])){
                        //transmet le pluriel et le genre
                        $this->arrFlux[$this->ordre]["vecteur"] = [
                            "pluriel"=> isset($this->arrFlux[$i]["vecteur"]["pluriel"]) ? $this->arrFlux[$i]["vecteur"]["pluriel"] : 0,
                            "genre"=> isset($this->arrFlux[$i]["vecteur"]["genre"]) ? $this->arrFlux[$i]["vecteur"]["genre"] : $this->defautGenre,
                        ];
                        $i=-1;
                    }
                }
    
            }
        }

        return $this->arrFlux[$this->ordre];

    }


    /**
     * Recupère le vecteur genre nombre
     *
     * @param string 	$type
     * @param string 	$dir
     * @param int 		$num
     * @param string 	$classType
     *
     */
	function getVecteur($type,$dir=-1,$num=1,$classType=false){
		
		//pour les verbes
		if($num==0)$num=1;
		
		$vecteur = false;
		$j = 1;
		if($dir>0){
			for ($i = $this->ordre; $i < count($this->arrFlux); $i++) {
				if(isset($this->arrFlux[$i]["vecteur"][$type])){
					if(!$classType){
						if($num == $j){
							return $this->arrFlux[$i]["vecteur"];
						}else{
							$j ++;							
						}
					}else{
						//pour éviter de récupérer le vecteur d'un adjectif
						if(isset($this->arrFlux[$i][$classType])){
							if($num == $j){
								return $this->arrFlux[$i]["vecteur"];
							}else{
								$j ++;							
							}
						}
					}
				}
			}
		}
		if($dir<0){
			for ($i = $this->ordre; $i >= 0; $i--) {
				//on récupère le vecteur 
				if(isset($this->arrFlux[$i]["vecteur"][$type])){
					if(!$classType){
						if($num == $j){
							return $this->arrFlux[$i]["vecteur"];
						}else{
							$j ++;							
						}
					}else{
						//pour éviter de récupérer le vecteur d'un adjectif
						if(isset($this->arrFlux[$i][$classType])){
							if($num == $j){
								return $this->arrFlux[$i]["vecteur"];
							}else{
								$j ++;							
							}
						}
					}
				}
			}
		}
		return $vecteur;
	}
		

    /**
     * renvoie les données pour enregistrer l'item
     *
     * @param boolean $detail
     * @return array
     */
    public function getData($detail=true)
    {
        if($detail){

            //TODO:problème encodage Ï	cf. http://localhost/genlod/omk/admin/item/353886        
            foreach ($this->arrFlux as $f) {
                if(isset($f['gen'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['@value'] = $f['gen'];
                    $valueObject['type'] = 'literal';
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;                    
                }
                if(isset($f['determinant']) && $f['determinant']){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['value_resource_id'] = $f['determinant']->id();
                    $valueObject['type'] = 'resource';        
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;                    
                }
                if(isset($f['item'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['value_resource_id'] = $f['item']->id();
                    $valueObject['type'] = 'resource';        
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;                    
                }
                if(isset($f['verbe']) && isset($f['temps'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['@value'] ='temps = '.$f['temps'].' - terminaison = '.$f['terminaison'];
                    $valueObject['type'] = 'literal';
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;                    
                }                        
                if(isset($f['txt'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['@value'] = $f['txt'];
                    $valueObject['type'] = 'literal';
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;                    
                }
                if(isset($f['img'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['foaf']['img']->id();
                    $valueObject['@id'] = $f['img'];
                    $valueObject['type'] = 'uri';
                    $this->data[$this->properties['foaf']['img']->term()][] = $valueObject;                    
                }
                if(isset($f['vecteur'])){
                    $vecteur = 'vecteur : ';
                    foreach ($f['vecteur'] as $k => $v) {
                        $vecteur .= $k.' = '.$v.', ';
                    }
                    $vecteur = substr($vecteur, 0, -2);
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['@value'] = $vecteur;
                    $valueObject['type'] = 'literal';
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;
                }
                if(isset($f['jdc'])){
                    $valueObject = [];
                    $valueObject['property_id'] = $this->properties['genex']['hasFlux']->id();
                    $valueObject['@value'] = json_encode($f['jdc']);
                    $valueObject['type'] = 'literal';
                    $this->data[$this->properties['genex']['hasFlux']->term()][] = $valueObject;
                }
            }
        }

        $this->data['o:resource_class'] = ['o:id' => $this->resourceClasses['genex']['Generation']->id()];
        
        if($this->texte){
            $valueObject = [];
            $valueObject['property_id'] = $this->properties['bibo']['content']->id();
            $valueObject['@value'] = $this->texte;
            $valueObject['type'] = 'literal';
            $this->data[$this->properties['bibo']['content']->term()][] = $valueObject;                    
        }
    
        return  $this->data;  
    }

    /**
     * génération à partir d'un concept
     *
     * @param o:Item    $oItem The item of generate
     * @param boolean   $generate génère la possibilité choisi
     * @param integer   $niveau profondeur de la génération
     * @return array
     */
    public function genConcept($oItem, $generate=true, $niveau=0)
    {
           
        //récupère les possibilités
        $possis = $this->getPossis($oItem);
        
        if(!$possis) throw new RuntimeException("Le concept '".$oItem->displayTitle()."' (".$oItem->id().") n'a pas de possibilité.");			

        //vérifie si on traite un caract
        $isCaract = $oItem->value('genex:isCaract') ? $oItem->value('genex:isCaract')->asHtml() : false;
        if($isCaract){
            //vérifie si le choix est déjà fait
            if(isset($this->arrCaract[$oItem->id()]))$id=$this->arrCaract[$oItem->id()];
            else $id=$this->arrCaract[$oItem->id()]=$possis[array_rand($possis)];
        }else
            //choisi une possibilité
            $id = $possis[array_rand($possis)];
        $v = $this->api->read('items', $id)->getContent();
        if($generate)
            //génère la possibilité
            return $this->generate(null, $v, $niveau+1);
        else
            return $v;

    }

    /**
     * récupère les possibilité d'un item
     *
     * @param o:Item $oItem The item of generate
     * @return array
     */
    public function getPossis($oItem)
    {   

        $success = false;
        $ids = [];
        $c = "getPossis".$oItem->id();        
        //if($this->bCache)
        //    $ids = $this->cache->getItem($c, $success);

        if (!$success) {

            //récupère les possibilités
            //TODO:gérer les langues 'lang' => 'es'
            $props = ['genex:hasTerm','genex:hasGenerateur'];
            $possis = [];
            foreach ($props as $p) {
                $vals = $oItem->value($p,['all' => true]);
                if($vals)$possis = array_merge($possis,$vals);
            }
            //vérifie s'il faut chercher les items liés
            if(!$possis){
                $query = ['property'=>[
                    ['joiner'=>'and','property'=>$this->properties['genex']['hasConcept']->id(),'type'=>'res','text'=>$oItem->id()]
                ]];
                $possis = $this->api->search('items', $query)->getContent();
            }
            //construction du tableau d'id
            foreach ($possis as $p) {
                if(get_class($p) == "Omeka\Api\Representation\ItemRepresentation"){
                    $ids[]=$p->id();
                }elseif(get_class($p) == "Omeka\Api\Representation\ValueRepresentation" 
                    && $p->type()=='resource'){
                    $vr = $p->valueResource();
                    $ids[]=$vr->id();                    
                }else{
                    throw new RuntimeException("Le concept n'est pas lié à une ressource valide.");			
                }                
            }

            $this->cache->setItem($c, $ids);

        }
        return $ids;
    }

    /**
     * génération à partir d'une requête sparql
     *
     * @param o:Item    $oItem The item of generate
     * @param integer   $niveau profondeur de la génération
     * @return array
     */
    public function genSparql($oItem, $niveau)
    {
        if(!isset($this->client)) $this->client = new Client();
        $this->client->setConfig(array(
            'timeout'      => 30
        ));                        
        $ep = $oItem->value('genex:sparqlEndpoint')->uri();    
        $q = $oItem->value('genex:sparqlQuery')->__toString();    
        $this->arrFlux[$this->ordre]["query"] = $q;
        $this->arrFlux[$this->ordre]["endpoint"] = $ep;
        if($this->log)$this->log->info(__METHOD__.' '.$oItem->id().' => '.$ep.' = '.$q);

        $client = $this->client->setUri($ep)->setParameterGet([
            'output' => 'json',
            'query' => $q,
        ]);
        $client->setHeaders(array(
          'Accept' => 'application/sparql-results+json',
         ));
        $response = $client->send();
        if (!$response->isSuccess()) {
            return [];
        }

        $results = json_decode($response->getBody(), true);
        foreach ($results['results']['bindings'] as $result) {
            $this->arrFlux[$this->ordre]["txt"] = $result['itemLabel']['value'];
            $this->arrFlux[$this->ordre]["img"] = $result['image']['value'];
        }

        return $this->arrFlux;

    }

    /**
     * génération à partir d'un générateur
     *
     * @param o:Item    $oItem The item of generate
     * @param integer   $niveau profondeur de la génération
     * @param string    $texte séquence générative  
     * 
     * @return array
     */
    public function genGen($oItem, $niveau, $texte="")
    {   
        if(!$texte)
            $texte = $oItem->value('dcterms:description') ? $oItem->value('dcterms:description')->__toString() : false;
        if(!$texte) return [];    
        $this->arrFlux[$this->ordre]["gen"] = $texte;
        if($this->log)$this->log->info(__METHOD__.' '.$oItem->id().' = '.$texte);

        if($this->arrFlux[$this->ordre]["niveau"] > $this->maxNiv){
            $erreur = "problème de boucle trop longue : ";
            for ($i=0; $i <= $niveau; $i++) { 
                $id = isset($this->arrFlux[$this->ordre-$i]["item"]) ? $this->arrFlux[$this->ordre-$i]["item"]->id() : -1;
                $erreur .= " - ".$id;
            }
            $this->arrFlux[$this->ordre]["ERREUR"] = $erreur;                           
            throw new RuntimeException($erreur);			
        }
    
        //parcourt l'ensemble du flux
        $fluxGen = $this->getFluxGen($texte);
        foreach ($fluxGen as $gen) {
            //on garde le même ordre si un seul flux
            if(count($fluxGen)>1)$this->ordre ++;
            //$this->arrFlux[$this->ordre]["niveau"] = $this->arrFlux[$this->ordre-1]["niveau"]+1;
            //si le flux a déjà un item on passe à l'ordre suivant
            if(isset($this->arrFlux[$this->ordre]["item"])){
                $this->ordre ++;
                //$this->arrFlux[$this->ordre]["niveau"] = $this->arrFlux[$this->ordre-1]["niveau"];
            }
            //sortie de boucle quand trop long
            $t = microtime(true)-$this->timeDeb;
            if($t > $this->timeMax){
                $this->arrFlux[$this->ordre]["ERREUR"] = "problème d'exécution trop longue : ".$gen['deb']." ".$gen['fin'];			
                $this->arrFlux[$this->ordre]["texte"] = "~";
                $this->arrSegment[$this->segment]["ordreFin"]= $this->ordre;
                break; 
            }
            if(isset($gen['txt']))$this->arrFlux[$this->ordre]["txt"]=$gen['txt'];
            if(isset($gen['compo'])){
                //calcule le terminant en premier pour définir le nombre
                if(isset($gen['compo']['det']))$this->getDeterminant($gen['compo']['det']);
                $this->getClass($gen['compo']['class'],$niveau);
            }
        }
        
        return $this->arrFlux;

    }

    /**
     * récupère un déterminant 
     *
     * @param string $det
     * 
     * @return boolean
     *
    */
	public function getDeterminant($det){


        $intDet = intval($det);
        $oItem = false;

        //vérifie si le vecteur est transmis
        if(substr($det,0,1)=='='){
            $place = intval(substr($det,1));
            for($i = $this->ordre-$place; $i >= 0; $i--){
                if(isset($this->arrFlux[$i]["vecteur"])){
                    //transmet le pluriel et le genre
                    $this->arrFlux[$this->ordre]["vecteur"] = [
                        "pluriel"=> isset($this->arrFlux[$i]["vecteur"]["pluriel"]) ? $this->arrFlux[$i]["vecteur"]["pluriel"] : 0,
                        "genre"=> isset($this->arrFlux[$i]["vecteur"]["genre"]) ? $this->arrFlux[$i]["vecteur"]["genre"] : $this->defautGenre,
                    ];
                    return $this->arrFlux[$this->ordre]["vecteur"];
                }
            }
        }        

        //vérifie si le déterminant est pour un verbe
        if(strlen($det) > 6){
			$this->arrFlux[$this->ordre]["determinant_verbe"] = $det;
	        return true;
        }       	
        
        //vérifie s'il faut chercher le pluriel
        $pluriel = false;
        if($intDet >= 50){
        	$pluriel = true;
        	$det = $intDet-50;
        }       			
        //vérifie s'il faut chercher le déterminant
        if($det!=0){
            $query = [
                'resource_class_id'=>[$this->resourceClasses['genex']['Determinant']->id()],
                'property'=>[
                ['joiner'=>'and','property'=>$this->properties['dcterms']['description']->id()
                ,'type'=>'eq','text'=>'determinant'.$det]
                ]
            ];
            $oItem = $this->api->search('items', $query)->getContent()[0];    
        }

		//ajoute le vecteur
		$this->arrFlux[$this->ordre]["vecteur"]["pluriel"] = $pluriel; 
        
        //ajoute le déterminant
		$this->arrFlux[$this->ordre]["determinant"] = $oItem;

        return $oItem;
    }



    /**
     * Calcul d'une class de generateur
     *
     * @param array     $class
     * @param integer   $niveau profondeur de la génération
     *
    */
	public function getClass($class, $niveau){

        if($this->log)$this->log->info(__METHOD__.' '.$class);
        $this->arrFlux[$this->ordre]["class"] = $class;        

		//gestion du changement de position de la classe
		$arrPosi=explode("@", $class);
        if(count($arrPosi)>1){
            $niveau ++; 
            $this->arrFlux[$this->ordre]["niveau"] = $niveau;        
            $ordreDeb = $this->ordre;
            $this->ordre ++;
            //calcul la chaine générative
            foreach ($arrPosi as $c) {
                $this->getClass($c, $niveau);
                $niveau ++; 
                $this->ordre ++;
            }
            $niveau --; 
            $this->ordre --;
            //supprime les déterminants sauf le premier d'un term
            //met à jour les vecteurs
            $sup = false;
            for ($i=$ordreDeb; $i <= $this->ordre; $i++) { 
                if($sup)unset($this->arrFlux[$i]["determinant"]);
                if(isset($this->arrFlux[$i]["rc"]) && $this->arrFlux[$i]["rc"]=="genex:Term")$sup=true;
                if(isset($this->arrFlux[$i]["vecteur"])){
                    $this->arrFlux[$i]["vecteur"]["genre"] = $this->arrFlux[$this->ordre]["vecteur"]["genre"] ? $this->arrFlux[$this->ordre]["vecteur"]["genre"] : $this->defautGenre; 
                    $this->arrFlux[$i]["vecteur"]["pluriel"] = $this->arrFlux[$ordreDeb]["vecteur"]["pluriel"] ? $this->arrFlux[$ordreDeb]["vecteur"]["pluriel"] : false; 
                }
            }
        	return;
        }            
        $oItem = $this->getItemByClass($class);        
        return $this->generate(null, $oItem, $niveau+1);

	}


    /**
	 * recupère le réseau d'un concept
     * 
     * @param o:Item    $oItem
     * @param int       $niv
     * @param array     $itemSrc
     * 
     * 
     * @return array
	 */
    function getConceptReseau($oItem, $niv=0, $itemSrc=false){

        //gestion du cache
        $success = false;
        $c = "getConceptReseau".$oItem->id();        
        if($this->bCache)
            $reseau = $this->cache->getItem($c, $success);

        if (!$success) {
            $class = $oItem->resourceClass()->term();
            if($class!='genex:Concept')
                throw new RuntimeException("Impossible de récupérer le réseau.<br/>La ressource n'est pas une class 'genex:Concept' mais '".$class."'");

            $desc = $oItem->value('dcterms:description')->asHtml();
            if(!$desc)
                throw new RuntimeException("Impossible de récupérer le réseau. Il n'y a pas de description");
            
            //récupère le reseau des genex:hasConcept pour ce concept
            $this->getItemReseau($oItem,$this->properties['genex']['hasConcept'],$niv,$niv == 0);

            //ajoute le lien vers la source
            if($itemSrc){
                $this->reseau['links'][]=array("source"=>$itemSrc->id(),"target"=>$oItem->id(),"niv"=>$niv,"value"=>1,"type"=>"genex:hasConcept");
            }

            //$this->trace("récupère les générateurs inclu dans les items");
            $nb = count($this->reseau['nodes']);
            for ($i=0; $i < $nb; $i++) {
                $oI = $this->reseau['nodes'][$i]['o'];
                $class = $oI->resourceClass()->term();
                $flux = $this->reseau['nodes'][$i]['flux'];
                //vérifie qu'il y a un générateur et qu'il n'est pas déjà traité
                if($class == 'genex:Generateur' && $desc && !$flux){
                    $desc =  $oI->value('dcterms:description')->asHtml();
                    $genFlux = $this->getFluxGen($desc);
                    $this->reseau['nodes'][$i]['flux']=$genFlux;                
                    foreach ($genFlux as $f) {
                        //$this->trace("calcul le réseau des générateurs inclus",$f);
                        if($f['gen'] && $niv < $this->maxNivReseau){
                            $o = $this->getItemByClass($f['compo']['class']);
                            $this->getConceptReseau($o,$niv+1,$oI);
                        }
                    }

                }
            }
            $this->cache->setItem($c, $this->reseau);
        }

        //renvoit le réseau du concept et de tous ces enfants
        return $this->reseau;
    }    

    /**
	 * récupère la décomposition du générateur
     * @param string $class
     * 
     * @return o:item
	 */
    function getItemByClass($class){

        $c = "getItemByClass".md5($class);
        $success = false;        
        if($this->bCache)
            $id = $this->cache->getItem($c, $success);

        if (!$success) {

            //récupère l'item par l'identifiant s'il est défini
            if(substr($class,0,5)=='oItem')
                $id = substr($class,5);
            else{
                $query = ['property'=>[
                    ['joiner'=>'and','property'=>$this->properties['dcterms']['description']->id(),'type'=>'eq','text'=>$class]
                ]];
                //TODO:trouver requête plus rapide pour éviter le cache ?
                //on ne retourne que l'id
                $items = $this->api->search('items',$query,['returnScalar'=>'id'])->getContent();
                
                if(count($items)==0)
                    throw new RuntimeException("Impossible de récupérer le concept. Aucun '".$class."' n'a été trouvés");
                /*on prend le premier par défaut
                if(count($items)>1)
                    throw new RuntimeException("Impossible de récupérer le concept. Plusieurs items ont été trouvés");
                */
    
                $id = $items[0];    
            }

            if($this->bCache)
                $this->cache->setItem($c, $id);
        }
        $oItem = $this->api->read('items', $id)->getContent();

        return $oItem;
    }

    /**
	 * récupère la décomposition du générateur
     * @param string $gen
     * 
     * @return array
	 */
    function getGenCompo($gen){

        $genCompo = array();

       
        //on récupère le déterminant
        $arrGen = explode("|",$gen);
        
        if(count($arrGen)==1){
		    if(strpos($gen,"#")){
                $genCompo['det'] = str_replace("#","",$gen); 
            }else{
                $genCompo['class']=$arrGen[0];
            }
            return $genCompo;
        }

        $genCompo['det']=$arrGen[0];
        $genCompo['class']=$arrGen[1];

		//gestion du changement de position de la classe
		$genCompo['posi']=explode("@", $genCompo['class']);
        
		//vérifie si la class est un déterminant
		if(is_numeric($genCompo['class']))$genCompo['classDet']=$genCompo['class'];

		//vérifie si la class possède un type de class
		if(strpos($genCompo['class'],"_")){
            $arr = explode("_",$genCompo['class']);			
            $genCompo['type']=$arr[0];
            $genCompo['gen']=$arr[1];
		}elseif(substr($genCompo['class'],0,5)=="carac"){
			//la class est un caractère
            $genCompo['caract'] = str_replace("carac", "carac_", $genCompo['class']);
		}elseif(strpos($genCompo['class'],"#")){
				$genCompo['gen'] = str_replace("#","determinant",$genCompo['class']); 
        }elseif(substr($genCompo['class'],0,1)=="=" && is_numeric(substr($genCompo['class'],1,1))){
            $genCompo['bloc'] = substr($genCompo['class'],1);
        }elseif(substr($genCompo['class'],0,1)=="=" && substr($genCompo['class'],1,1)=="x"){
            $genCompo['bloc'] = substr($genCompo['class'],1);
        }elseif(strpos($genCompo['class'],"-")){
            $genCompo['gen']=$genCompo['class'];
        }			

        return $genCompo;

    }

/**
	 * récupère la décomposition du générateur
     * @param string $gen
     * 
     * @return array
	 */
    function explodeCode($gen){

        $genCompo = array();
       
        //on récupère le déterminant
        $arrGen = explode("|",$gen);
        
        if(count($arrGen)==1){
		    if(strpos($gen,"#")!== false){
                $genCompo['syn'] = $gen; 
            }else{
                $genCompo['class']=$arrGen[0];
                $genCompo['cpt1']=$arrGen[0];        
            }
            return $genCompo;
        }else{
            $genCompo['det']=$arrGen[0];
            $genCompo['class']=$arrGen[1];                
        }
        $genCompo['det']=$genCompo['det']=="0" ? "" : $genCompo['det'];
		//vérifie si la class est un déterminant
		//if(is_numeric($genCompo['class']))$genCompo['classDet']=$genCompo['class'];

		//décompose la classe
		$arrClass=explode("@", $genCompo['class']);
        $genCompo['cpt1']=$arrClass[0];        
        $genCompo['cpt2']=$arrClass[1];        

        return $genCompo;

    }


    /**
	 * contruction du flux de génération
     * @param string $exp
     * 
     * @return array
	 */
    function getFluxGen($exp){


        //vérification du texte conditionnel                
        $posCondi = strpos($exp, '<');
        if ($posCondi !== false) {    
            //choisi s'il faut afficher le texte conditionnel
            $a = mt_rand(0, 1000);        
            if($a>500){
                //on suprime les caractères conditionnels
                $exp = str_replace("<", "", $exp);                        
                $exp = str_replace(">", "", $exp);                        
            }else{
                //on suprime le texte entre le '<' et le '>'
                $posCondiFin = strpos($exp, '>');
                $exp = substr($exp, 0, $posCondi).substr($exp, $posCondiFin+1);                        
            }
        }

        //récupère les générateurs du texte
        $arrGen = $this->getGenInTxt($exp);

        //construction du flux
        $arrFlux = array();
        $posi = 0;
        foreach ($arrGen[0] as $i => $gen) {
            //retrouve la position du gen
            $deb = strpos($exp, $gen, $posi);
            $fin = strlen($gen)+$deb;
            if($deb>$posi){
                $txt = substr($exp, $posi, $deb-$posi);
                $arrFlux[]=array('deb'=>$posi,'fin'=>$deb,'txt'=>$txt);                
            }
            //décompose le générateur
            $genCompo = $this->getGenCompo($arrGen[1][$i]);
            $arrFlux[]=array('deb'=>$deb,'fin'=>$fin,'gen'=>$arrGen[1][$i],'compo'=>$genCompo);
            $posi = $fin;
        }
        //vérifie s'il faut ajouter la fin du texte
        if($posi<strlen($exp))$arrFlux[]=array('deb'=>$posi,'fin'=>strlen($exp),'txt'=>substr($exp, $posi));

        return $arrFlux;

    }  

    /**
	 * recupère les générateurs d'une expression textuelle
     * merci à https://stackoverflow.com/questions/10104473/capturing-text-between-square-brackets-in-php
     * @param string $exp
     * 
     * @return array
	 */
    function getGenInTxt($exp){

        preg_match_all("/\[([^\]]*)\]/", $exp, $matches);
        return $matches;

    }

    //TODO:mettre les fonctions suivantes dans le modules JDC

    /**
     * Fonction pour intialiser le cache
     *
     */
    function initCache(){

        // Via factory:
        $this->cache = StorageFactory::factory([
            'adapter' => [
                'name'    => 'filesystem',
                'options' => array('ttl' => 31536000, 'cache_dir' => __DIR__.'/cache/')
                //this->serviceLocator
                //'options' => array('ttl' => 1800)
            ],

            'plugins' => [
                'exception_handler' => ['throw_exceptions' => false],
                'serializer'
            ],
        ]); 

        /*TOTDO use doctrine cache
        https://www.doctrine-project.org/projects/doctrine-cache/en/1.11/index.html
        use Doctrine\Common\Cache\MemcacheCache;
        $memcache = new Memcache();
        $cache = new MemcacheCache();
        $cache->setMemcache($memcache);

        $cache->set('key', 'value');

        echo $cache->get('key') // prints "value"
        */

    }

    /**
     * Fonction pour récupérer le réseau d'un item
     *
     * @param array	    $item
     * @param string	$prop
     * @param int	    $niv
     * @param boolean	$init
	 * 
     * @return	array
     *
     */
    function getItemReseau($item, $prop, $niv=0, $init=true){

        /*
        $c = "getItemReseau".$item['o:id'];
        if(!$this->bCache)
           $this->cache->remove($c);
        $reseau = $this->cache->load($c);

        if(!$reseau) {
        */

            if($init){
                $this->doublons = array();
                $this->reseau = array('nodes'=>array(),'links'=>array());    
            }

            $this->gereDoublons($item);

            $this->getItemLiensEntrant($item, $prop, $niv);
            $this->getItemLiensSortant($item, $prop, $niv);
        /*    
            $this->cache->save($this->omk->reseau, $c);
            $reseau = $this->omk->reseau;
        }
        */
    
        return $this->reseau;

	}        


    function gereDoublons($item){
        //vérifie les doublons et incrémente la taille du noeud
        if(isset($this->doublons[$item->id()])){
            $this->reseau['nodes'][$this->doublons[$item->id()]]['nb']++;
        }else{
            $nb = count($this->reseau['nodes']);
            $this->doublons[$item->id()]= $nb ? $nb-1 : $nb;
            $this->reseau['nodes'][]=['o'=>$item,'nb'=>1,'flux'=>false,'id'=>$item->id(),'title'=>$item->displayTitle()];
        }         
    }


   /**
     * Fonction pour récupérer les liens sortant d'un item
     *
     * @param o:Item	$item
     * @param string	$prop
     * @param int	    $niv
	 * 
     * @return	array
     *
     */
    function getItemLiensSortant($item, $prop, $niv=0){
        
        //récupère les items qui ont l'item en lien sortant
        $query = ['property'=>[
                ['joiner'=>'and','property'=>$prop->id(),'type'=>'res','text'=>$item->id()]
            ]];
        $items = $this->api->search('items',$query,['limit'=>0])->getContent();    

		foreach ($items as $i) {
            $this->gereDoublons($i);                
            //enregistre le lien
            $this->reseau['links'][]=array("source"=>$item->id(),"target"=>$i->id(),"niv"=>$niv,"value"=>1,"type"=>$prop->term());
            //recherche les liens de la target
            $this->getItemLiensSortant($i, $prop, $niv+1);
        }
        return $this->reseau;
	}        

    /**
     * Fonction pour récupérer les liens entrant d'un item
     *
     * @param array	    $item
     * @param string	$prop
     * @param int	    $niv
	 * 
     * @return	array
     *
     */
    function getItemLiensEntrant($item, $prop, $niv=0){

        //ajoute les liens de l'item
        $relations = $item->subjectValues();
        foreach ($relations as $k => $r) {
            foreach ($r as $v) {
                $vr = $v->resource();
                $this->gereDoublons($vr);                
                $this->reseau['links'][]=array("source"=>$vr->id(),"target"=>$item->id(),"niv"=>$niv,"value"=>1,"type"=>$prop->term());
            }
        }
        return $this->reseau;
    }    

    /**
     * Cache selected resource classes.
     */
    public function cacheResourceClasses()
    {
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $classes = $this->api->search('resource_classes', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($classes as $class) {
                $this->resourceClasses[$prefix][$class->localName()] = $class;
            }
        }
    }

    /**
     * Cache selected properties.
     */
    public function cacheProperties()
    {
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $properties = $this->api->search('properties', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($properties as $property) {
                $this->properties[$prefix][$property->localName()] = $property;
            }
        }
    }

    /**
     * Cache selected resource template.
     */
    public function cacheResourceTemplate()
    {
        foreach ($this->resourcesTemplates as $label) {
            $rts = $this->api->search('resource_templates', [
                'label' => $label,
            ])->getContent();
            foreach ($rts as $rt) {
                $this->resourceTemplate[$label]=$rt;
            }

        }
    }    

}
