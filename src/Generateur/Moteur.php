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
    var $temps = array(
        array('num'=>1,'lib'=>'indicatif présent'),
        array('num'=>2,'lib'=>'indicatif imparfait'),
        array('num'=>3,'lib'=>'passé simple'),
        array('num'=>4,'lib'=>'futur simple'),
        array('num'=>5,'lib'=>'conditionnel présent'),
        array('num'=>6,'lib'=>'subjonctif présent'),
        array('num'=>8,'lib'=>'participe présent'),
        array('num'=>9,'lib'=>'infinitif'),
    );
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
        'lexinfo'       => 'http://www.lexinfo.net/ontology/3.0/lexinfo#',
        'genex'        => 'https://jardindesconnaissances.univ-paris8.fr/onto/genex#',
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

    /**
     * Construct Moteur
     *
     * @param objet     $api The api for communicate with omeka
     * @param boolean   $bCache active le cache
     */
    public function __construct($api, $bCache=true)
    {
        $this->api = $api;
        $this->cacheResourceClasses();
        $this->cacheResourceTemplate();
        $this->cacheProperties();
        $this->bCache = $bCache;
        $this->initCache();

    }

    /**
     * The generate with data
     *
     * @param array     $data The data source of generate
     * @param o:item    $oItem item à générer
     * 
     * @return array
     */
    public function generate($data, $oItem=false)
    {   
        if(!isset($this->data)){
            $this->data = $data;
            $this->arrFlux = array();
            $this->ordre = 0;
            $this->segment = 0;    
            $this->timeDeb = microtime(true);
            $oItem = $this->api->read('items', $this->data['o:resource']['o:id'])->getContent();
        }
        $rc  = $oItem->resourceClass()->term();    
        $this->arrFlux[$this->ordre]["item"] = $oItem;
        $this->arrFlux[$this->ordre]["niveau"] = $this->niv;
        $this->arrFlux[$this->ordre]["rc"] = $rc;

        //generates according to class
        switch ($rc) {
            case "genex:Generateur":
                $this->genGen($oItem);
                break;            
            case "genex:Concept":
                $this->genConcept($oItem);
                break;                                
            case "genex:Term":
                //rien de particulier à faire
                break;                                
            default:
                //si class inconnu on ne fait rien
                break;
        }
        return $this->arrFlux;
    }

    /**
     * génération à partir d'un concept
     *
     * @param o:Item $oItem The item of generate
     * @return array
     */
    public function genConcept($oItem)
    {   

        //récupère les possibilités
        //TODO:gérer les langues 'lang' => 'es'
        $props = ['genex:hasTerm','genex:hasGenerateur'];
        $possis = [];
        foreach ($props as $p) {
            $vals = $oItem->value($p,['all' => true]);
            if($vals)$possis = array_merge($possis,$vals);
        }
        //récupère une des possibilités
        $v = $possis[array_rand($possis)];
        if($v->type()=='resource'){
            $vr = $v->valueResource();
            //génère une des posibilités de manière aléatoire
            return $this->generate(null, $vr);
        }else{
            throw new RuntimeException("Le concept n'est pas lié à une ressource valide.");			
        }
        
    }

    /**
     * génération à partir d'un générateur
     *
     * @param o:Item $oItem The item of generate
     * @return array
     */
    public function genGen($oItem)
    {   

        $texte = $oItem->value('dcterms:description')->asHtml();    
        $this->arrFlux[$this->ordre]["generation"] = $texte;
        if($this->niv > $this->maxNiv){
            $this->arrFlux[$this->ordre]["ERREUR"] = "problème de boucle trop longue : ".$texte;			
            throw new Exception("problème de boucle trop longue.<br/>".$this->detail);			
        }
    
        //parcourt l'ensemble du flux
        $fluxGen = $this->getFluxGen($texte);
        foreach ($fluxGen as $gen) {
            $this->ordre ++;
            $this->arrFlux[$this->ordre]["niveau"] = $this->niv;
            //sortie de boucle quand trop long
            $t = microtime(true)-$this->timeDeb;
            if($t > $this->timeMax){
                $this->arrFlux[$this->ordre]["ERREUR"] = "problème d'exécution trop longue : ".$gen['deb']." ".$gen['fin'];			
                $this->arrFlux[$this->ordre]["texte"] = "~";
                $this->arrSegment[$this->segment]["ordreFin"]= $this->ordre;
                break; 
            }
            if(isset($gen['compo']))$this->calculClass($gen['compo']['class']);

        }
        
        return $this->arrFlux;

    }


    /**
     * Calcul d'une class de generateur
     *
     * @param array $class
     *
    */
	public function calculClass($class){


		//gestion du changement de position de la classe
		$arrPosi=explode("@", $class);
        if(count($arrPosi)>1){
        	$this->arrPosi=explode("@", $class);
        	//"récupère le vecteur du déterminant ".$this->ordre;
        	$vDet = $this->arrFlux[$this->ordre]["vecteur"];
        	$ordreDet = $this->ordre;
        	//change l'ordre pour que la class substantif soit placée après
        	$this->ordre ++;
        	$this->arrFlux[$this->ordre]["vecteur"] = $vDet; 
        	//calcul le substantifs
        	$this->calculClass($this->arrPosi[1]);
        	$vSub = $this->arrFlux[($this->ordre)]["vecteur"];
        	//redéfini l'ordre pour que la class adjectif soit placée avant
        	$this->ordre --;
        	//avec le vecteur du substantif
        	$this->arrFlux[$this->ordre]["vecteur"] = $vSub; 
        	//calcul l'adjectif
        	$this->calculClass($this->arrPosi[0]);
        	$vAdj = $this->arrFlux[($this->ordre-1)]["vecteur"];        	
        	//rédifini l'élision et le genre du déterminant avec celui de l'adjectif
        	$this->arrFlux[$ordreDet]["vecteur"]["elision"]=$vAdj["elision"];
        	$this->arrFlux[$ordreDet]["vecteur"]["genre"]=$vSub["genre"];
        	
        	return;
        }

		$this->arrFlux[$this->ordre]["class"] = $class;
        $oItem = $this->getItemByClass($class);
        
        return $this->generate(null, $oItem);

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
        $query = ['property'=>[
            ['joiner'=>'and','property'=>$this->properties['dcterms']['description']->id(),'type'=>'eq','text'=>$class]
        ]];
        $items = $this->api->search('items',$query,['limit'=>0])->getContent();
        
        if(count($items)>1)
            throw new RuntimeException("Impossible de récupérer le concept. Plusieurs items ont été trouvés");
        
        return $items[0];

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
