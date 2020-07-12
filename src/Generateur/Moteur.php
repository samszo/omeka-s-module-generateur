<?php
 /*
 * @author Samuel Szoniecky
 * @category   Zend
 * @package library\Flux\Outils
 * @license https://creativecommons.org/licenses/by-sa/2.0/fr/ CC BY-SA 2.0 FR
 * @version  $Id:$
 */
namespace Generateur\Generateur;

use Omeka\Api\Exception\RuntimeException;
use Zend\Cache\StorageFactory;

class Moteur {

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
            7=>'indicatif présent',
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
    ];
    /**
     * Resource template to cache.
     *
     * @var array
     */
    protected $resourcesTemplates = ["genex_Concept","genex_Conjugaison","genex_Generateur","genex_Term"];
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
    protected $c;
    /**
     * Tableau du reseau
     *
     * @var array
     */
    protected $reseau = [];
    /**
     * Cache du module
     *
     * @var Zend_Cache
     */
    protected $cache;
    /**
     * Activation du Cache
     *
     * @var boolean
     */
    protected $bCache;
    /**
     * variable de generation
     */
    var $arrFlux = array();
    var $ordre = 0;
    var $segment = 0;
    var $niv=0;
    var $maxNiv = 10;
	var $timeDeb;
    var $timeMax = 1000;
    var $texte = "";
	var $finLigne = "<br/>";
    var $coupures=array();
    var $lang = 'fr';
	var $arrEli = array("a", "e", "é", "ê", "i","o","u","y","h");
	var $arrEliCode = array(195);
    var $arrCaract = array();

    /**
     * Construct Moteur
     *
     * @param object     $api The api for communicate with omeka
     * @param boolean   $bCache active le cache
     * @param object    $c controller pour gérer : les logs
     */
    public function __construct($api, $bCache=true, $c)
    {
        $this->api = $api;
        $this->cacheResourceClasses();
        $this->cacheResourceTemplate();
        $this->cacheProperties();
        $this->bCache = $bCache;
        $this->initCache();
        $this->c = $c;

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
            $this->data = $data;
            $this->arrFlux = array();
            $this->ordre = 0;
            $this->segment = 0;    
            $this->timeDeb = microtime(true);
            $oItem = $this->api->read('items', $this->data['o:resource']['o:id'])->getContent();
        }
        $rc  = $oItem->resourceClass()->term();    
        $this->c->logger()->info(__METHOD__.' item id ='.$oItem->id());

        $this->arrFlux[$this->ordre]["niveau"] = $niveau;
        $this->arrFlux[$this->ordre]["item"] = $oItem;
        $this->arrFlux[$this->ordre]["rc"] = $rc;

        //generates according to class
        switch ($rc) {
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
					//vérifie le texte conditionnel
					if($arr["txt"]=="<"){
				        //choisi s'il faut afficher
				        $a = mt_rand(0, 1000);        
				        if($a>500){
				        	$txtCondi = false;
					        //vérifie si le texte conditionnel est imbriqué
					        //pour sauter à la fin de la condition
					        if(isset($this->arrFlux[$i+2]["txt"]) && $this->arrFlux[$i+2]["txt"]=="|"){
					        	for ($j = $this->ordre; $j <= $ordreFin; $j++) {
					        		if($this->arrFlux[$j]["txt"]=="|"
					        			&& $this->arrFlux[$j+1]["txt"]==$this->arrFlux[$i+1]["txt"]
						        		&& $this->arrFlux[$j+2]["txt"]==">"){
						        			$i=$j+2;
						        			$j=$ordreFin;		
					        		}
					        	}
					        }
				        }else{
					        //vérifie si le texte conditionnel est imbriqué
					        //attention pas plus de 10 imbrications
					        if(isset($this->arrFlux[$this->ordre+2]["txt"]) && $this->arrFlux[$this->ordre+2]["txt"]=="|"){
					        	$imbCondi[$this->arrFlux[$this->ordre+1]["txt"]] = true;
					        	$i+=2;
					        }
				        }
					}elseif($arr["txt"]==">"){
				        //vérifie les conditionnels imbriqué
				        if($imbCondi){
				        	if(isset($imbCondi[$this->arrFlux[$this->ordre-1]["txt"]])){
					        	$txtCondi = true;
					        	$c = substr($this->texte,-2,1);
					        	if($c=="|"){
						        	$this->texte = substr($this->texte,0,-2);
					        	}
					        	//supprime la condition imbriquée
					        	unset($imbCondi[$this->arrFlux[$this->ordre-1]["txt"]]);
					        }else{
					        	//cas des conditions dans condition imbriquée
								$txtCondi = true;					        	
					        }
				        }else{
							$txtCondi = true;
				        }
					}elseif($arr["txt"]=="{"){
						//on saute les crochets de test
					    for ($j = $this->ordre; $j <= $ordreFin; $j++) {
					    	if($this->arrFlux[$j]["txt"]=="}"){
					    		$i = $j+1;	
					    	}
					    }
					}elseif($txtCondi){
						if($arr["txt"]=="%"){
							$texte .= $this->finLigne;	
						}else{
							$texte .= $arr["txt"];
						}
					}
                    $this->texte .= $texte;
				}elseif(isset($arr["ERREUR"]) && $this->showErr){
					$this->texte .= '<ul><font color="#de1f1f" >ERREUR:'.$arr["ERREUR"].'</font></ul>';	
				}elseif(isset($arr["item"])){	
                    $this->arrFlux[$i]=$this->genereItem($arr);				
                    $this->texte .= $this->arrFlux[$i]["txt"]." ";
				}
			}
		}
		
        //nettoie le texte
        $this->nettoyer();

        //gestion des majuscules
        $this->genereMajuscules();

		//mise en forme du texte
		$this->coupures();


    }

    /**
     * Fonction pour nettoyer le texte
     *
     *
     */
	function nettoyer(){

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
        if($arr['prefixConj']){
            $prefix = "";
            $vPrefix = $arr['item']->value('genex:hasPrefix',['lang' => $this->lang,'all'=>true]);
            if(count($vPrefix)==2) $prefix = $vPrefix[1]->asHtml();
            else $prefix = $vPrefix[0]->asHtml();
            $centre = $prefix.$arr['terminaison'];
        }else
            $centre = $arr['terminaison'];
		
		//construction de l'élision
		$eli = $this->isEli($centre);
		
        $verbe="";

        if(isset($arr['prodem'])){
            $prodem['lib'] = $arr['prodem']->value('genex:hasPrefix') ? $arr['prodem']->value('genex:hasPrefix',['lang' => $this->lang])->asHtml() : "";
            $prodem['lib_eli'] = $arr['prodem']->value('genex:hasElision') ? $arr['prodem']->value('genex:hasElision',['lang' => $this->lang])->asHtml() : "";
            $prodem['num'] = $arr['prodem']->value('dcterms:description') ? $arr['prodem']->value('dcterms:description',['lang' => $this->lang])->asHtml() : "";
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
			if($arr["determinant_verbe"][1]==9 || $arr["determinant_verbe"][1]==7){
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
                $lib = $arr['prosuj']->value('genex:hasPrefix') ? $arr['prosuj']->value('genex:hasPrefix',['lang' => $this->lang])->asHtml() : "";
                $lib_eli = $arr['prosuj']->value('genex:hasElision') ? $arr['prosuj']->value('genex:hasElision',['lang' => $this->lang])->asHtml() : "";
				if($this->isEli($verbe)){
					//vérification de l'apostrophe
					if (strrpos($lib_eli, "'") === false) { 
						$verbe = $lib_eli." ".$verbe; 
					}else
						$verbe = $lib_eli.$verbe; 
				}else{
					$verbe = $lib." ".$verbe; 
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
        $conj = $arr['item']->value('genex:hasConjugaison',['all'=>true]);
        $idConj = false;
        foreach ($conj as $c) {
            if($c->type()=='resource'){
                $oConj = $c->valueResource();
                $idConj = $oConj->id();                    
            }                
        }
        if(!$idConj)
            throw new RuntimeException("La conjugaison pour '".$arr['item']->displayTitle()."' n'a pas été trouvée.");			

        //vérifie si la conjugaison nécessite un prefix
        $arr['prefixConj'] = $oConj->value('genex:hasPrefix') ? boolval($oConj->value('genex:hasPrefix')->asHtml()) : true;
            
		//gestion des terminaisons ---
		$txt = $this->getTerminaison($idConj,$arr['temps'],$p[0],$p[1]);
		if($txt=="---")$txt="";		
        $arr['terminaison']=$txt;        

        return $arr;
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
        return $term->asHtml();            

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
			if($numP==6 || $numP==7){
                $arr["terminaison"] = $numP==6 ? 3 : 6;				
				//il/elle singulier pluriel
                $pr = $this->getItemByClass("a_il");
                $arr["prosuj"] = $this->genConcept($pr,false);
                //récupère le pronom suivant le genre et le nombre
                $vecteur = $this->getVecteur('genre');
                $p = $numP==7 ? 'lexinfo:pluralNumberForm' : 'lexinfo:singularNumberForm';
                $lib = $arr['prosuj']->value($p,['all'=>true]);
                $arr["prosujlib"] = $lib[$vecteur['genre']-1]->asHtml();        
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
                    $prodem = $arr["prodem"]->value('genex:hasElision',['lang' => $this->lang])->asHtml();
                else
                    $prodem = $arr["prodem"]->value('genex:hasPrefix',['lang' => $this->lang])->asHtml();
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
        if(!isset($vecteur["genre"]))$vecteur["elision"]="2";
        $oItem = $arr['determinant'];
		if($vecteur){			
            $this->c->logger()->info(__METHOD__.' '.$oItem->id(),$vecteur);
            if($vecteur['pluriel'])
                $formDet = $oItem->value('lexinfo:pluralNumberForm',['lang' => $this->lang, 'all'=>true]);
            else
                $formDet = $oItem->value('lexinfo:singularNumberForm',['lang' => $this->lang, 'all'=>true]);

            if($vecteur["elision"]=="0" && $vecteur["genre"]=="1"){
				$det = $formDet[1]->asHtml()." ";
			}
			if($vecteur["elision"]=="0" && $vecteur["genre"]=="2"){
				$det = $formDet[0]->asHtml()." ";
			}
			if($vecteur["elision"]=="1" && $vecteur["genre"]=="1"){
				$det = $formDet[3]->asHtml()." ";
			}
			if($vecteur["elision"]=="1" && $vecteur["genre"]=="2"){
				$det = $formDet[2]->asHtml()." ";
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
        $this->c->logger()->info(__METHOD__.' '.$oItem->id());
        //generates according to class
        switch ($item['rc']) {
            case "genex:Generateur":
                if(isset($item["determinant"])){
                    $item = $this->genereDeterminant($item);
                    $txt = $item['txtDeterminant'];    
                }
                break;                                
            case "genex:Term":
                $prefix = $oItem->value('genex:hasPrefix',['lang' => $this->lang])->asHtml();
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
                                && $item["vecteur"]['genre']=="2" ? $term[0]->asHtml() : $term[1]->asHtml();
                        }else
                            $term = $term ? $term[0]->asHtml() : "";                            
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
     */
	function setVecteurTerm($oItem, $niveau){

        //si le term n'a pas de genre : il n'y a pas de vecteur
        //if(!$oItem->value('lexinfo:gender')) return;

        //si le term a un déterminant de verbe : pas de vecteur
        if(isset($this->arrFlux[$this->ordre]["determinant_verbe"])) return;
        
        //ajoute les vecteurs
        $elision = $oItem->value('genex:hasElision',['lang' => $this->lang]) ? $oItem->value('genex:hasElision',['lang' => $this->lang])->asHtml() : "0";//pas d'élision par défaut
        $genre = $oItem->value('lexinfo:gender',['lang' => $this->lang]) ? $oItem->value('lexinfo:gender',['lang' => $this->lang])->asHtml() : "2";//masculin par défaut
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
        for ($i = $niveau; $i > 0; $i--) {
            if(isset($this->arrFlux[$this->ordre-1]["vecteur"])){
                $this->arrFlux[$this->ordre]["vecteur"]["pluriel"] = $this->arrFlux[$this->ordre-1]["vecteur"]["pluriel"];    
                $this->arrFlux[$this->ordre-1]["vecteur"]["genre"]=$genre;
                $this->arrFlux[$this->ordre-1]["vecteur"]["elision"] = $elision;                    
            }
            if(isset($this->arrFlux[$this->ordre-1]["determinant"])){
                $this->arrFlux[$this->ordre]["determinant"] = $this->arrFlux[$this->ordre-1]["determinant"];    
            }
            if(isset($this->arrFlux[$this->ordre-1]["determinant_verbe"])){
                $this->arrFlux[$this->ordre]["determinant_verbe"] = $this->arrFlux[$this->ordre-1]["determinant_verbe"];    
            }
        }

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
     * @return array
     */
    public function getData()
    {
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
     * génération à partir d'un générateur
     *
     * @param o:Item    $oItem The item of generate
     * @param integer   $niveau profondeur de la génération
     * @return array
     */
    public function genGen($oItem, $niveau)
    {   

        $texte = $oItem->value('dcterms:description')->asHtml();    
        $this->arrFlux[$this->ordre]["gen"] = $texte;
        $this->c->logger()->info(__METHOD__.' '.$oItem->id().' = '.$texte);

        if($this->arrFlux[$this->ordre]["niveau"] > $this->maxNiv){
            $this->arrFlux[$this->ordre]["ERREUR"] = "problème de boucle trop longue : ".$texte;			
            throw new Exception("problème de boucle trop longue.<br/>".$this->detail);			
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
                        "genre"=> isset($this->arrFlux[$i]["vecteur"]["genre"]) ? $this->arrFlux[$i]["vecteur"]["genre"] : 2,
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
            $oItem = $this->api->searchOne('items', $query)->getContent();    
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

        $this->c->logger()->info(__METHOD__.' '.$class);

		//gestion du changement de position de la classe
		$arrPosi=explode("@", $class);
        if(count($arrPosi)>1){
        	//change l'ordre pour que la class substantif soit placé après
        	$this->ordre ++;
        	//calcul le substantifs
            $this->getClass($arrPosi[1], $niveau);
            $vSub = $this->arrFlux[$this->ordre]["vecteur"];
        	//redéfini l'ordre pour que la class adjectif soit placée avant
        	$this->ordre --;
        	//avec le vecteur genre du substantif
        	$this->arrFlux[$this->ordre]["vecteur"]["genre"] = $vSub["genre"]; 
        	//calcul l'adjectif et le déterminant
            $this->getClass($arrPosi[0], $niveau);
            //rédifini l'élision du déterminant avec celui de l'adjectif
            $oAdj = $this->arrFlux[$this->ordre]['item'];
            $elision = $oAdj->value('genex:hasElision',['lang' => $this->lang]) ? $oAdj->value('genex:hasElision',['lang' => $this->lang])->asHtml() : "0";//par d'élision par défaut
            $this->arrFlux[$this->ordre]["vecteur"]["elision"] = $elision;    
            //rédifini le nombre du substantif avec celui du determinant et de l'adjectif
            $this->arrFlux[$this->ordre+1]["vecteur"]["pluriel"] = $this->arrFlux[$this->ordre]["vecteur"]["pluriel"];    
        	return;
        }

        $this->arrFlux[$this->ordre]["class"] = $class;        
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
            $query = ['property'=>[
                ['joiner'=>'and','property'=>$this->properties['dcterms']['description']->id(),'type'=>'eq','text'=>$class]
            ]];
            $items = $this->api->search('items',$query)->getContent();
            
            if(count($items)==0)
                throw new RuntimeException("Impossible de récupérer le concept. Aucun '".$class."' n'a été trouvés");
            /*on prend le premier par défaut
            if(count($items)>1)
                throw new RuntimeException("Impossible de récupérer le concept. Plusieurs items ont été trouvés");
            */

            $oItem = $items[0];
            $this->cache->setItem($c, $oItem->id());
        }else
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
            $genCompo['class']=$arrGen[0];
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
	 * contruction du flux de génération
     * @param string $exp
     * 
     * @return array
	 */
    function getFluxGen($exp){

        //récupère les générateur du texte
        $arrGen = $this->getGenInTxt($exp);

        //construction du flux
        $arrFlux = array();
        $posi = 0;
        foreach ($arrGen[0] as $i => $gen) {
            //retrouve la position du gen
            $deb = strpos($exp, $gen, $posi);
            $fin = strlen($gen)+$deb;
            if($deb>$posi)$arrFlux[]=array('deb'=>$posi,'fin'=>$deb,'txt'=>substr($exp, $posi, $deb-$posi));
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
                //'options' => array('ttl' => 1800)
            ],

            'plugins' => [
                'exception_handler' => ['throw_exceptions' => false],
                'serializer'
            ],
        ]); 

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
