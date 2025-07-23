<?php
namespace Generateur\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Generateur\Generateur\Moteur;

class GenerateurSql extends AbstractHelper
{
    protected $api;
    protected $cnx;
    protected $logger;
    protected $cache;
    protected $moteur;
    protected $rs;
    protected $temps = [
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
    var $personnes = [
        1=>['lexinfo:firstPersonForm',0,391,1],        
        2=>['lexinfo:secondPersonForm',0,381,2],        
        3=>['lexinfo:thirdPersonForm',0,352,3],        
        4=>['lexinfo:firstPersonForm',1,391,1],        
        5=>['lexinfo:secondPersonForm',1,381,2],        
        6=>['lexinfo:thirdPersonForm',1,352,3]        
    ];
    /**
     * @var Acl
     */
    protected $acl;

    /* plus nécessaire car la base est nettoyée
    var $idsDicosPropres = [4 , 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163];
    */
    public function __construct($api, $cnx, $logger, $acl)
    {
        $this->logger = $logger;
        $this->api = $api;
        $this->cnx = $cnx;
        $this->acl = $acl;
    }

    /**
     * Execution de requêtes sql directement dans la base sql
     *
     * @param array     $params paramètre de l'action
     * @return array
     */
    public function __invoke($params=[])
    {
        if($params==[])return[];
        switch ($params['action']) {
            case 'getConceptLinksPast':
                $result = $this->getOldConceptLinks($params);
                break;          
            case 'getOldConceptDoublons':
                $result = $this->getOldConceptDoublons($params['cpt'],$params['limit'],$params['offset']);
                break;
            case 'getOldConceptUses':
                $result = $this->getOldConceptUses($params['cpt']);
                break;          
            case 'getStatsOldConceptUses':
                $result = $this->getStatsOldConceptUses($params);
                break;
            case 'explodeGenerateur':
                $result = $this->explodeGenerateur($params);
                break;
            case 'explodeConcept':
                $result = $this->explodeConcept($params);
                break;
            case 'getOldConceptOubli':
                $result = $this->getOldConceptOubli("http://localhost/omk_generateur/modules/Generateur/data/imports/gen_conceptsOubli.csv");
                break;
            case 'getRandomFlux':
                $result = $this->getRandomFlux($params);
                break;                                                                                  
            case 'deleteOeuvre':
                //ATTENTION DANGEREUX : la suppression d'une oeuvre supprime TOUTES les ressources associées 
                $result = $this->deleteOeuvre($params['idOeu']);
                break;
            case 'deleteDico':
                //ATTENTION DANGEREUX : la suppression d'un DICO supprime TOUTES les ressources associées 
                $result = $this->deleteDico($params['idDico']);
                break;
            case 'deleteConcept':
                //ATTENTION DANGEREUX : la suppression d'un concept supprime TOUTES les ressources associées 
                $result = $this->deleteResource($params['id']);
                break;
            case 'getDicoItems':
                $result = $this->getDicoItems($params);
                break;
            case 'getConceptTerms':
                $result = $this->getConceptTerms($params);
                break;
            case 'getConjModels':
                $result = $this->getConjModels($params);
                break;
            case 'exportDico':
                $result = $this->exportDico($params);
                break;
            case 'exportCpt':
                $result = $this->exportCpt($params);
                break;
            case 'getOeuvreUses':
                $result = $this->getOeuvreUses($params);
                break;
        }                      

        return $result;

    }    

    function getOeuvreUses($params){
        $query = "SELECT
            count(distinct vHasDico.value_resource_id) nbDico,
            group_concat(distinct vHasDico.value_resource_id) idsDico
            , count(distinct vTerm.resource_id) nbItem
            , rTerm.resource_class_id
            , cTerm.label class
            from resource r
            inner join value vHasDico on vHasDico.resource_id = r.id and vHasDico.property_id = 501
            inner join value vDicoNonGen on vDicoNonGen.resource_id = vHasDico.value_resource_id and vDicoNonGen.property_id = 508 and vDicoNonGen.value = 'non'
            inner join value vTerm on vTerm.value_resource_id = vDicoNonGen.resource_id and vTerm.property_id = 501
            inner join resource rTerm on rTerm.id = vTerm.resource_id
            inner join resource_class cTerm on cTerm.id = rTerm.resource_class_id
            where r.id = ?
            group by rTerm.resource_class_id";
        return $this->cnx->fetchAll($query,[$params['idOeu']]);
    }   

    function getCnx(){
        return $this->cnx;
    }

    /**
     * initialise le cache
     *
     * @return void
     */
    function initCache(){
        if(!isset($this->cache)){
            set_time_limit(0);
            $this->cache = [];
            $this->cache['terms'] = [];
            $this->cache['codes'] = [];
            $this->cache['concepts'] = [];
            $this->cache['determinants'] = [];
            $this->cache['oeuvresCpt'] = [];
            $this->cache['accords'] = []; 
            $this->cache['pronoms'] = [];           
        }
    }   

    /**
     * export en csv les élements d'un dictionnaire
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function exportDico($params){
        /*ATTENTION :
        l'ordre des champs est important
        le nombre de champs est important
        */
        $cols = array(['concept','description_concept','type_concept','term','type_term','description_term','generateur','prefix','gender','conjugaison',
        'ehasElisionlision','accordFemSing','accordFemPlu','accordMasSing','accordMasPlu']);
        $cpts = $this->getDicoItems($params);
        foreach ($cpts as $cpt) {
            $vals = [
                'concept' => $cpt['title'],
                'description_concept' => $cpt['description'],
                'type_concept' => $cpt['type'],
            ];
            //récupère les terms associés
            $terms = $this->getConceptTerms(["idCpt"=>$cpt['id']]);
            foreach ($terms as $k => $t) {
                if($k!=0){
                    $vals = [
                        'concept' => "",
                        'description_concept' => "",
                        'type_concept' => ""
                    ];
                }                
                $vals['term'] = $t['title'];
                $vals['type_term'] = $t['type'];
                $vals['description_term'] = $t['description'];
                $vals['generateur'] = $t['generateur'];
                $vals['prefix'] = isset($t['prefix']) ? $t['prefix'] : '';
                $vals['gender'] = isset($t['gender']) ? $t['gender'] : '';
                $vals['conjugaison'] = isset($t['conj']) ? $t['conj'] : '';
                if(isset($t['accords'])){
                    $accords = [];
                    foreach (explode(",",$t['accords']) as $a) {
                        $pv = explode(" : ",$a);
                        $accords[$pv[0]]=$pv[1];
                    }
                    $vals['hasElision'] = isset($accords['hasElision']) ? $accords['hasElision'] : '';
                    $vals['accordFemSing'] = isset($accords['accordFemSing']) ? $accords['accordFemSing'] : '';
                    $vals['accordFemPlu'] = isset($accords['accordFemPlu']) ? $accords['accordFemPlu'] : '';
                    $vals['accordMasSing'] = isset($accords['accordMasSing']) ? $accords['accordMasSing'] : '';
                    $vals['accordMasPlu'] = isset($accords['accordMasPlu']) ? $accords['accordMasPlu'] : '';
                }else{
                    $vals['hasElision'] = '';
                    $vals['accordFemSing'] = '';
                    $vals['accordFemPlu'] = '';
                    $vals['accordMasSing'] = '';
                    $vals['accordMasPlu'] = '';
                }
                $cols[] = $vals;
            }
            if(count($terms)==0){
                $vals['term'] = $t['title'];
                $vals['type_term'] = $t['type'];
                $vals['description_term'] = $t['description'];
                $vals['generateur'] = $t['gen'];
                $vals['prefix'] = isset($t['prefix']) ? $t['prefix'] : '';
                $vals['gender'] = isset($t['gender']) ? $t['gender'] : '';
                $vals['conjugaison'] = isset($t['conj']) ? $t['conj'] : '';
                $vals['hasElision'] = '';
                $vals['accordFemSing'] = '';
                $vals['accordFemPlu'] = '';
                $vals['accordMasSing'] = '';
                $vals['accordMasPlu'] = '';
                $cols[] = $vals;
            }
        };
        $dico = $this->api->read("items",$params['idDico'])->getContent();        
        return ['fileName'=>'exportDico-'.$dico->displayTitle().".csv",'csv'=>$this->array2csv($cols)];
    }

    /**
     * export en csv les élements d'un concept
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function exportCpt($params){
        /*ATTENTION :
        l'ordre des champs est important
        le nombre de champs est important
        */
        $cols = array(['concept','description_concept','type_concept','term','type_term','description_term','generateur','prefix','gender','conjugaison',
        'ehasElisionlision','accordFemSing','accordFemPlu','accordMasSing','accordMasPlu']);
        $cpt = $this->api->read("items",$params["idCpt"])->getContent();
        $vals = [
            'concept' => $cpt->displayTitle(),
            'description_concept' => $cpt->value('dcterms:description')->__toString(),
            'type_concept' => $cpt->value('genex:hasType')->__toString()
        ];
        //récupère les terms associés
        $terms = $this->getConceptTerms(["idCpt"=>$params["idCpt"]]);
        foreach ($terms as $k => $t) {
            if($k!=0){
                $vals = [
                    'concept' => "",
                    'description_concept' => "",
                    'type_concept' => ""
                ];
            }                
            $vals['term'] = $t['title'];
            $vals['type_term'] = $t['type'];
            $vals['description_term'] = $t['description'];
            $vals['generateur'] = $t['generateur'];
            $vals['prefix'] = isset($t['prefix']) ? $t['prefix'] : '';
            $vals['gender'] = isset($t['gender']) ? $t['gender'] : '';
            $vals['conjugaison'] = isset($t['conj']) ? $t['conj'] : '';
            if(isset($t['accords'])){
                $accords = [];
                foreach (explode(",",$t['accords']) as $a) {
                    $pv = explode(" : ",$a);
                    $accords[$pv[0]]=$pv[1];
                }
                $vals['hasElision'] = isset($accords['hasElision']) ? $accords['hasElision'] : '';
                $vals['accordFemSing'] = isset($accords['accordFemSing']) ? $accords['accordFemSing'] : '';
                $vals['accordFemPlu'] = isset($accords['accordFemPlu']) ? $accords['accordFemPlu'] : '';
                $vals['accordMasSing'] = isset($accords['accordMasSing']) ? $accords['accordMasSing'] : '';
                $vals['accordMasPlu'] = isset($accords['accordMasPlu']) ? $accords['accordMasPlu'] : '';
            }else{
                $vals['hasElision'] = '';
                $vals['accordFemSing'] = '';
                $vals['accordFemPlu'] = '';
                $vals['accordMasSing'] = '';
                $vals['accordMasPlu'] = '';
            }
            $cols[] = $vals;
        }
        if(count($terms)==0){
            $vals['term'] = $t['title'];
            $vals['type_term'] = $t['type'];
            $vals['description_term'] = $t['description'];
            $vals['generateur'] = $t['generateur'];
            $vals['prefix'] = isset($t['prefix']) ? $t['prefix'] : '';
            $vals['gender'] = isset($t['gender']) ? $t['gender'] : '';
            $vals['conjugaison'] = isset($t['conj']) ? $t['conj'] : '';
            $vals['hasElision'] = '';
            $vals['accordFemSing'] = '';
            $vals['accordFemPlu'] = '';
            $vals['accordMasSing'] = '';
            $vals['accordMasPlu'] = '';
            $cols[] = $vals;
        }
        return ['fileName'=>'exportConcept-'.$cpt->displayTitle().".csv",'csv'=>$this->array2csv($cols)];
    }

    function array2csv($data, $delimiter = ',', $enclosure = '"', $escape_char = "\\")
    {
        $f = fopen('php://memory', 'r+');
        $first = true;
        foreach ($data as $item) {
            if(!$first)$item=array_values($item);            
            fputcsv($f, $item, $delimiter, $enclosure, $escape_char);
        }
        rewind($f);
        return stream_get_contents($f);
    }

    /**
     * renvoie les élements d'un dictionnaire
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function getDicoItems($params){
        //récupère les générateurs à décomposer avec leurs concepts
        $query = "SELECT rLR.id, rLR.title, rLR.resource_class_id, rLR.resource_template_id, 
                rcLR.local_name, vLRt.value type, vLRdesc.value description
            from resource r
            inner join value vLR on vLR.value_resource_id = r.id
            inner join resource rLR on rLR.id = vLR.resource_id
            inner join resource_class rcLR on rcLR.id = rLR.resource_class_id
            inner join value vLRt on vLRt.resource_id = rLR.id AND vLRt.property_id = 196
            left join value vLRdesc on vLRdesc.resource_id = rLR.id AND vLRdesc.property_id = 4
            where r.id = ?";
        return $this->cnx->fetchAll($query,[$params['idDico']]);
    }

/**
     * renvoie la liste des modèles de conjugaison
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function getConjModels($params){
        //récupère les générateurs à décomposer avec leurs concepts
        $query = "SELECT r.id, r.title
        FROM resource r
        inner join value vDico on vDico.resource_id = r.id and vDico.property_id = 501
        inner join value vOeuvre on vOeuvre.value_resource_id = vDico.value_resource_id and vOeuvre.property_id = 501
        inner join resource rOeuvre on rOeuvre.id = vOeuvre.resource_id and rOeuvre.resource_class_id = 409
        where r.resource_class_id=110 and rOeuvre.id=?
        order by r.title";
        return $this->cnx->fetchAll($query,[$params['idOeu']]);
    }   

    /**
     * renvoie les terms d'un concept
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function getConceptTerms($params){
        //récupère les générateurs à décomposer avec leurs concepts
        $query = "SELECT rLR.id, rLR.title, rLR.resource_class_id, rLR.resource_template_id, 
                rcLR.local_name, vLRt.value type, vDesc.value description
                , vPrefix.value prefix
                , vGender.value gender
                , vConj.value conj
                , vEli.value eli
                , vGen.value gen
                , vAccord.resource_id accord_id
                , vAccord.accords
from resource r
inner join value vLR on vLR.value_resource_id = r.id
inner join resource rLR on rLR.id = vLR.resource_id
inner join resource_class rcLR on rcLR.id = rLR.resource_class_id
inner join value vLRt on vLRt.resource_id = rLR.id AND vLRt.property_id = 196
left join value vPrefix on vPrefix.resource_id = rLR.id AND vPrefix.property_id = 185
left join value vGender on vGender.resource_id = rLR.id AND vGender.property_id = 339
left join value vConj on vConj.resource_id = rLR.id AND vConj.property_id = 193
left join value vEli on vEli.resource_id = rLR.id AND vEli.property_id = 195
left join value vGen on vGen.resource_id = rLR.id AND vGen.property_id = 187
left join value vAnnoId on vAnnoId.resource_id = rLR.id AND vAnnoId.property_id = 506
left join value vDesc on vDesc.resource_id = rLR.id AND vDesc.property_id = 4
left join (SELECT 
    GROUP_CONCAT(CONCAT(p.local_name,' : ',v.value)) accords,
    v.resource_id
FROM
    value v
    inner join property p on p.id = v.property_id
GROUP BY v.resource_id    
    ) vAccord on vAccord.resource_id = vAnnoId.value_annotation_id
where r.id = ?";
        return $this->cnx->fetchAll($query,[$params['idCpt']]);
    }    
    /**
     * décompose un générateur en ses composants
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function explodeGenerateur($params){
        $this->initCache();
        if(!isset($params['niv']))$params['niv']=1;
        if($this->cache['terms']["explode".$params['idTerm']]!=null){
            //$this->logger->info('Le terme {idTerm} a déjà été traité.', $params);
            return;
        }
        $this->cache['terms']["explode".$params['idTerm']] = $params['idTerm'];
        $this->logger->info(
            'Décomposition des générateurs : {idTerm} -  {niv}.', // @translate
            $params
        );
        //création du moteur
        $this->moteur = new Moteur(false,$this->api,$this->logger);

        //récupère les générateurs à décomposer avec leurs concepts
        $query = "SELECT 
            r.id idTerm,
            v.value,
            GROUP_CONCAT(DISTINCT vCpt.value_resource_id) idsCpt
        FROM
            resource r
                INNER JOIN
            value v ON v.resource_id = r.id
                AND v.property_id = 187
                INNER JOIN
            value vCpt ON vCpt.resource_id = r.id
                AND vCpt.property_id = 189 
            WHERE r.resource_class_id = 107 AND r.id = ?
            GROUP BY r.id ORDER BY r.id ";
        $rs = $this->cnx->fetchAll($query,[$params['idTerm']]);
        foreach ($rs as $i => $v) {
            //if($i<9294)continue;
            $this->logger->info(
                'Traitement du term - '.$i.' : {idTerm} = {value} ({idsCpt}).', // @translate
                $v
            );

            //supprime les enregistrements pour ce term
            $delete = "DELETE FROM gen_flux WHERE ordre_id IN (SELECT id FROM gen_ordre WHERE term_id = ?)";
            $this->cnx->executeQuery($delete,[$v['idTerm']]);
            $delete = "DELETE FROM gen_ordre WHERE term_id = ?";
            $this->cnx->executeQuery($delete,[$v['idTerm']]);
            //$this->logger->info('Terme supprimé'.' : '.$v['idTerm']);

            //met à jour les relations
            $this->updateOeuvreDicoConceptTerm($v['idTerm']);

            //vérification du texte conditionnel                
            $posCondiDeb = strpos($v['value'], '<');
            $posCondiFin = strpos($v['value'], '>');
            //on supprimer les marques du conditionnel
            $v['value'] = str_replace("<", "", $v['value']);
            $v['value'] = str_replace(">", "", $v['value']);
    
            //récupère la place des générateurs dans la valeur
            preg_match_all('/\[(.*?)\]/', $v['value'], $gens, PREG_OFFSET_CAPTURE);
            //enregistre les générateurs
            foreach ($gens[1] as $g) {
                //récupère le code du générateur   
                $idCode = $this->getIdCode($g[0]);                     
                //ajoute l'ordre dans le générateur
                $insert = "INSERT INTO gen_ordre (term_id, code_id, ordre, conditionnel) 
                    VALUES (?, ?, ?, ?)";                          
                $this->cnx->executeQuery($insert,[$v['idTerm'],$idCode['id'],$g[1],$g[1] > $posCondiDeb && $g[1] < $posCondiFin ? 1 : 0]);
                $idOrdre=$this->cnx->lastInsertId();    

                /*on enregistre la décomposition pour chaque concept et chaque oeuvre 
                car un concept est lié à un dictionnaire lui même en rapport avec des oeuvres ayant leurs propres dictionnaires
                la recherche des valeurs ce fait dans les dictionnaires associés aux oeuvres
                */
                $idsCpt = explode(",",$v['idsCpt']);
                foreach ($idsCpt as $idCpt) {
                    //Récupère les oeuvres associées au concept pour avoir les dictionnaires de références
                    $oeuvres = $this->getConceptsOeuvres($idCpt);
                    foreach ($oeuvres as $o) {

                        /*construction des valeurs
                        on prend en priorité la valeur de cpt1 puis on vérifie si value n'est pas un concept dans cette oeuvre
                        */
                        $idCpt1 = isset($idCode['cpt1']) ? $this->getIdCpt($idCode['cpt1'],$o['idsDico']) : null;
                        $idCpt1 = !isset($idCpt1) && !isset($idCode['det']) && !isset($idCode['syn']) ? $this->getIdCpt($idCode['value'],$o['idsDico']) : $idCpt1;
                        $pInsert = [
                            'oeuvre_id' => $o['idOeuvre'],
                            'concept_id' => $idCpt,
                            'ordre_id' => $idOrdre,
                            'syn_id' => isset($idCode['syn']) ? $this->getIdDeterminant($idCode['syn'],$o['idsDico']) : null,
                            'det_id' => isset($idCode['det']) ? $this->getIdDeterminant($idCode['det'],$o['idsDico']) : null,
                            'cpt1_id' => $idCpt1,
                            'cpt2_id' => isset($idCode['cpt2']) ? $this->getIdCpt($idCode['cpt2'],$o['idsDico']) : null
                            ];
                        $insert = "INSERT INTO gen_flux (oeuvre_id, concept_id, ordre_id, syn_id, det_id, cpt1_id, cpt2_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $this->cnx->executeQuery($insert,[$pInsert['oeuvre_id'],$pInsert['concept_id'],$pInsert['ordre_id'],
                            $pInsert['syn_id'],$pInsert['det_id'],$pInsert['cpt1_id'],$pInsert['cpt2_id']]);

                        //explode les concepts associés
                        if($pInsert['cpt1_id']) $this->explodeConcept(["idConcept"=>$pInsert['cpt1_id'],'niv'=>$params['niv']+1]);
                        if($pInsert['cpt2_id']) $this->explodeConcept(["idConcept"=>$pInsert['cpt2_id'],'niv'=>$params['niv']+1]);
                        /*
                        $this->logger->info(
                            'Enregistrement du générateur : {oeuvre_id} - {concept_id} - {term_id} - {value} - {ordre} - {conditionnel} - {determinant} - {determinant_id} - {gen_ids}.', // @translate
                            $params
                        );
                        */
                    }
                }
            }
            
            //récupère les textes en dur
            $durs = preg_split('/\[[^\]]*\]/', $v['value'], -1, PREG_SPLIT_OFFSET_CAPTURE);
            //enregistre les textes en dur
            foreach ($durs as $d) {
                //récupère le code du générateur   
                $idCode = $this->getIdCode($d[0]);                     
                //ajoute l'ordre dans le générateur
                $insert = "INSERT INTO gen_ordre (term_id, code_id, ordre, conditionnel) 
                    VALUES (?, ?, ?, ?)";                          
                $this->cnx->executeQuery($insert,[$v['idTerm'],$idCode['id'],$d[1],$d[1] > $posCondiDeb && $d[1] < $posCondiFin ? 1 : 0]);
                $idOrdre=$this->cnx->lastInsertId();       
            }
            //$this->logger->info('Fin Traitement du term : '.$i);
        }
        return $this->cache;      

    }



    /**
     * décompose un concept en ses composants
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function explodeConcept($params){
        if(!isset($params['niv'])){
            $this->initCache();
            $params['niv']=1;
            $deb = microtime(true);
        }
        if($this->cache['concepts']["explode".$params['idConcept']]!=null){
            //$this->logger->info('Le concept {idConcept} a déjà été traité.', $params);
            return;
        }
        //ATTENTION le cache des concepts n'est pas le même pour la function explodeConcept que pour la function getIdCpt
        $this->cache['concepts']["explode".$params['idConcept']] = $params['idConcept'];

        $this->logger->info(
            "Décomposition d'un concept : {idConcept} - {niv}.", // @translate
            $params
        );
        //création du moteur
        $this->moteur = new Moteur(false,$this->api,$this->logger);

        //récupère les terms à décomposer
        $query = "SELECT
                r.id idTerm,
                vGen.value gen,
                vType.value type,
                vPrefix.value prefix
            FROM
                resource r
                    INNER JOIN value vCpt ON vCpt.resource_id = r.id AND vCpt.property_id = 189  AND vCpt.value_resource_id = ?  
                    LEFT JOIN value vGen ON vGen.resource_id = r.id AND vGen.property_id = 187
                    LEFT JOIN value vType ON vType.resource_id = r.id AND vType.property_id = 196
                    LEFT JOIN value vPrefix ON vPrefix.resource_id = r.id AND vPrefix.property_id = 185
            WHERE r.resource_class_id = 107  
            ";
        $rs = $this->cnx->fetchAll($query,[$params['idConcept']]);
        foreach ($rs as $i => $v) {
            //if($i<9294)continue;
            $this->logger->info(
                'Traitement du terme - '.$i.' : {idTerm}.', // @translate
                $v
            );

            //supprime les enregistrements pour ce term
            $delete = "DELETE FROM gen_flux WHERE ordre_id IN (SELECT id FROM gen_ordre WHERE term_id = ?)";
            $this->cnx->executeQuery($delete,[$v['idTerm']]);
            $delete = "DELETE FROM gen_ordre WHERE term_id = ?";
            $this->cnx->executeQuery($delete,[$v['idTerm']]);
            //$this->logger->info('Terme supprimé'.' : '.$v['idTerm']);

            //met à jour les relations
            $this->updateOeuvreDicoConceptTerm($v['idTerm']);

            //explose le générateur si besoin
            if($v['gen']!=null){
                //vérifie si le générateur est déjà en cache
                if(!isset($this->cache['terms']["explode".$v['idTerm']])){
                    $this->explodeGenerateur(['idTerm'=>$v['idTerm'],'niv'=>$params['niv']+1]);
                }
            }
            //$this->logger->info('Fin Traitement du term : '.$i);
        }
        if($params['niv']==1){
            $this->cache['elapsed_time'] = $this->getDuree($deb,microtime(true));
        }

        return $this->cache;      

    }



    /**
     * Récupère aléatoirement un flux d'un concept
     *
     * @param array   $params paramètre de la fonction
     *
    */
    function getRandomFlux($params){
        if(!isset($params['niv'])){
            $this->initCache();
            $params['niv']=1;
            $deb = microtime(true);
            $this->cache['idsDico'] = $this->getConceptsOeuvres($params['idConcept'])[0]['idsDico'];
        }

        //pas besoin des idOeuvre et IdDico $query = "SELECT odct.oeuvre_id, odct.dico_id, odct.concept_id, odct.term_id, 
        $query = "SELECT odct.concept_id cpt_id, odct.term_id, 
				vType.value type,
                o.ordre, o.conditionnel cond, 
                c.value, c.det det, c.cpt1, c.cpt2,
                f.syn_id, det_id, f.det_id det_id, f.cpt1_id, f.cpt2_id
            FROM gen_oeuvre_dico_concept_term odct
			LEFT JOIN value vType ON vType.resource_id = odct.term_id AND vType.property_id = 196
            LEFT JOIN gen_ordre o ON o.term_id = odct.term_id
            LEFT JOIN gen_code c ON c.id = o.code_id ";
        if(!isset($params['idTerm'])){
            $query .= " INNER JOIN
                (SELECT term_id
                FROM gen_oeuvre_dico_concept_term
                WHERE concept_id = ?
                ORDER BY RAND() LIMIT 1) as rndTerm  ON rndTerm.term_id = odct.term_id ";
        }  
        $query .= " LEFT JOIN gen_flux f ON f.ordre_id = o.id
        WHERE odct.concept_id = ? ";
        if(isset($params['idTerm'])) $query .= " AND odct.term_id = ? ";
        $query .= " GROUP BY o.term_id, o.ordre
            ORDER BY o.ordre";
        if(!isset($params['idTerm'])){
            $rs = $this->cnx->fetchAll($query,[$params['idConcept'],$params['idConcept']]);
            if(!$rs){
                //$this->logger->err('Pas de flux trouvé pour le concept : '.$idConcept);
                return ["error"=>'Pas de flux trouvé pour le concept : '.$params['idConcept']];
            }
        }else{
            $rs = $this->cnx->fetchAll($query,[$params['idConcept'],$params['idTerm']]);
            if(!$rs){
                //$this->logger->err('Pas de flux trouvé pour le concept : '.$idConcept.' et le terme '.$params['idTerm']);
                return ["error"=>'Pas de flux trouvé pour le concept '.$params['idConcept'].' et le terme '.$params['idTerm']];
            }
        }
        $nbOrdre = count($rs);         
        for ($i=0; $i < $nbOrdre; $i++) { 
            $v = $rs[$i];
            $rs[$i]['niv'] = $params['niv'];
            if($v['syn_id'])$this->getSynAccords($v['syn_id']);
            if($v['det_id'])$this->getTermAccords($v['det_id']);
            for ($j=1; $j < 3; $j++) { 
                if($v['cpt'.$j.'_id']){
                    $rs[$i]['cpt'.$j.'_flux'] = $this->getRandomFlux(['idConcept'=>$v['cpt'.$j.'_id'],'niv'=>$params['niv']+1]);
                }
            }
            if($v['type']!="generateur")$this->getTermAccords($v['term_id']);
            if(strlen($v['det'])>6)$this->getVerbeAccords($rs[$i], $this->cache['idsDico']);
            //vérifie si le génrateur est un verbe simple
            if($v['type']=="generateur" && $v['det']=="" && substr($v['value'],0,2)=="v_"){
                $rs[$i]['det'] = "01000000";//le déterminant est la troisième personne du singulier
                $this->getVerbeAccords($rs[$i], $this->cache['idsDico']);
            }

            //supprime les valeurs nulles
            $rs[$i] = array_filter($rs[$i], function($value) {
                return $value !== null;
            });
            //
        }
        if($params['niv']==1){
            $rs['accords']=array_filter($this->cache['accords'], function($value) {
                return $value['type'] !== "generateur";
            });
            $rs['elapsed_time'] = $this->getDuree($deb,microtime(true));
        }
        return $rs;
    }

    function getVerbeAccords($flux,$idsDico){ 

        //dans le cas d'un verbe théorique on ne fait rien
		if($flux["value"]=="v_théorique")return $flux; 

        //récupère le déterminant du verbe
        $this->getDetVerbe($flux['det'], $idsDico);
        
        //récupère le temps
        $mood = $this->temps[$flux["det"][1]];
        //récupère la personne
        switch ($flux["det"][2]) {
            case 7:
            case 9:
                $p = $this->personnes[6];
                break;            
            case 8:
            case 0:
                $p = $this->personnes[3];
                break;            
            default:
                $p = $this->personnes[$flux["det"][2]];
                break;
        }
    
        //récupère la terminaison
        $query="SELECT 
                vR.value_annotation_id,
                vAnno.value
            FROM resource r
            inner join value vR on vR.resource_id = r.id AND vR.property_id = 192 AND vR.value = ?
            inner join value vAnno on vAnno.resource_id = vR.value_annotation_id AND vAnno.property_id = ?
            WHERE r.id = ?
            LIMIT 1 OFFSET ".$p[1];
        $acc = $this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0];
        $rs = $this->cnx->fetchAssoc($query,[$mood,$p[2],$acc['idConj']]);
        $this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0]['tms']=$rs['value']=="---" || $rs['value']=="- " ? "" : $rs['value'];		

        //récupère la négation
        if($flux['det'][0]!=""){
            $query = "select r.id, vTitle.value lib 
                from resource r
                    inner join value vId on vId.resource_id = r.id and vId.property_id = 10 and vId.value = ?
                    inner join value vDico on vDico.resource_id = r.id and vDico.property_id = 501 and vDico.value_resource_id IN (".$idsDico.") 
                    inner join value vTitle on vTitle.resource_id = r.id and vTitle.property_id = 1
                where r.resource_class_id = 115 ";
            $rs = $this->cnx->fetchAssoc($query,[$flux['det'][0]]);
            $neg = $rs['value']=="-" ? " ":$rs['lib'];
        }else{
            $neg = "";
        }
             
        //complète les accords
        //$this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0]['temps']=$mood;		
        $this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0]['pluriel']=$p[1];		
        $this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0]['finneg']=$neg;		
        $this->cache['accords'][$flux['cpt1_flux'][0]['term_id']][0]['prs']=$p[3];		

    }


    function getDetVerbe($det,$idsDico){
        /*
        Position 0 : type de négation
        Position 1 : temps verbal
        Position 2 : pronoms sujets définis
        Positions 3 ET 4 : pronoms compléments
        Position 5 : ordre des pronoms sujets
        Position 6 : pronoms indéfinis
        Position 7 : Place du sujet dans la chaîne grammaticale
        */
        $kDet = "detVerbe".$det[2].$det[3].$det[4].$det[6];
        if(!isset($this->cache['accords'][$kDet])){
            $sujet = $det[2] ? $this->getPronom($det[2], 'sujet', $idsDico) : "";
            $comp = $det[3] || $det[4] ? $this->getPronom($det[3].$det[4], 'complement', $idsDico) : "";
            $indef = $det[6] ? $this->getPronom($det[6], 'sujet indéfini', $idsDico) : "";
            $this->cache['accords'][$kDet]=['s'=>$sujet,'c'=>$comp,'i'=>$indef];
        }
        return $this->cache['accords'][$kDet];
    }

    function getDuree($debut,$fin){
        $elapsed = $fin - $debut;
        $minutes = floor($elapsed / 60);
        $seconds = $elapsed % 60;
        return sprintf('%d minutes and %.2f seconds', $minutes, $seconds);
    }


    /**
     * Récupère les accords d'un syntagme
     *
     * @param integer     $idSyn l'identifiant du terme
     *
    */
    function getSynAccords($idSyn){


        if(!isset($this->cache['accords']["syn".$idSyn])){
            $query = "SELECT 
                r.id,
                r.resource_class_id class,
                vTitle.value lib 
                FROM resource r
                    INNER JOIN value vTitle ON vTitle.resource_id = r.id AND vTitle.property_id = 1
                WHERE r.id = ? ";
            $this->cache['accords']["syn".$idSyn] = $this->cnx->fetchAssoc($query,[$idSyn]);
        }
        return $this->cache['accords']["syn".$idSyn];
    }

    /**
     * Récupère les accords d'un terme
     *
     * @param integer     $idTerm l'identifiant du terme
     *
    */
    function getTermAccords($idTerm){


        if(!isset($this->cache['accords'][$idTerm])){

            $query = "SELECT 
r.id,
r.resource_class_id class,
vType.value type, 
vPrefix.value prefix,
vConj.value_resource_id	idConj,
vGenre.value genre,
vAnno.resource_id idAnno,
group_concat(vAnno.value) vals,
group_concat(vAnno.property_id) props
FROM resource r
	LEFT JOIN value vTerm ON vTerm.resource_id = r.id AND vTerm.property_id = 506
	LEFT JOIN value vAnno ON vAnno.resource_id = vTerm.value_annotation_id
	LEFT join value vType ON vType.resource_id = r.id AND vType.property_id = 196
	LEFT JOIN value vPrefix ON vPrefix.resource_id = r.id AND vPrefix.property_id = 185
	LEFT JOIN value vConj ON vConj.resource_id = r.id AND vConj.property_id = 193
	LEFT JOIN value vGenre ON vGenre.resource_id = r.id AND vGenre.property_id = 339
WHERE r.id = ?
group by vAnno.resource_id";
            $rs = $this->cnx->fetchAll($query,[$idTerm]);
            //
            if($rs){
                //supprime les valeurs nulles
                for ($i=0; $i < count($rs); $i++ ) { 
                    $rs[$i] = array_filter($rs[$i], function($value) {
                        return $value !== null;
                    });
                }
            }
            //
            $this->cache['accords'][$idTerm] = $rs;
        }
        return $this->cache['accords'][$idTerm];
    }


    /**
     * Récupère un pronom
     *
     * @param integer     $idTerm l'identifiant du terme
     *
    */
    function getPronom($id, $type, $idDicos){

        if(!isset($this->cache['pronoms'][$id.$type.'_'.$idDicos])){
            $query = "select r.id, vId.value ident
                , vTitle.value lib, vEli.value eli
                , vType.value type
                from resource r
                    inner join value vId on vId.resource_id = r.id and vId.property_id = 10 and vId.value = ?
                    inner join value vDico on vDico.resource_id = r.id and vDico.property_id = 501 and vDico.value_resource_id IN (".$idDicos.") 
                    inner join value vType on vType.resource_id = r.id and vType.property_id = 8 and vType.value = ?
                    inner join value vTitle on vTitle.resource_id = r.id and vTitle.property_id = 1
                    inner join value vEli on vEli.resource_id = r.id and vEli.property_id = 195
                where r.resource_class_id = 112";
                $rs = $this->cnx->fetchAssoc($query,[intval($id),$type]);
                if($rs['lib']=="[=1|a_zéro]")$rs['lib']="";
                if($rs['eli']=="[=1|a_zéro]")$rs['el']="";
                $this->cache['pronoms'][$id.$type.'_'.$idDicos] = $rs;
        }
        return $this->cache['pronoms'][$id.$type.'_'.$idDicos];
    }   

    /**
     * Met à jour les relations entre les oeuvre, les dicos, les concepts et les termes
     *
     * @param string     $gen le code générateur
     *
    */
    function updateOeuvreDicoConceptTerm($idTerm){
        $query = "DELETE FROM gen_oeuvre_dico_concept_term WHERE term_id = ?";
        $this->cnx->executeQuery($query,[$idTerm]);
        $query = "INSERT INTO gen_oeuvre_dico_concept_term(term_id, concept_id, dico_id, oeuvre_id)
            SELECT 
                r.id idTerm,
                vCpt.value_resource_id idCpt,
                vDico.value_resource_id idDico,
                vOeu.resource_id idOeu
            FROM resource r
                INNER JOIN value vCpt ON vCpt.resource_id = r.id AND vCpt.property_id = 189
                INNER JOIN value vDico ON vDico.resource_id = vCpt.value_resource_id AND vDico.property_id = 501
                INNER JOIN value vOeu ON vOeu.value_resource_id = vDico.value_resource_id AND vOeu.property_id = 501
                INNER JOIN resource rOeu ON rOeu.id = vOeu.resource_id AND rOeu.resource_class_id = 409
            WHERE
                r.resource_class_id = 107 AND r.id = ?";
        $this->cnx->executeQuery($query,[$idTerm]);
    }

    /**
     * Récupère l'identifiant du code
     *
     * @param string     $gen le code générateur
     *
    */
	public function getIdCode($gen){
        //vérifie si la classe est déjà en cache
        if(isset($this->cache['codes'][$gen])){
            return $this->cache['codes'][$gen];
        }
        //ATTENTION la recherche est case sensistive
        $query = "SELECT * FROM gen_code WHERE BINARY value = ?";
        $rsCode = $this->cnx->fetchAssoc($query,[$gen]);
        if(!$rsCode) {
            //décompose le générateur
            $compo = $this->moteur->explodeCode($gen);
            //ajoute le code du générateur                        
            $insert = "INSERT INTO gen_code (value, syn, det, cpt1, cpt2) 
                VALUES (?, ?, ?, ?, ?)";
            $this->cnx->executeQuery($insert,[$gen,$compo['syn'],$compo['det'],$compo['cpt1'],$compo['cpt2']]);
            $this->cache['codes'][$gen]=$this->cnx->fetchAssoc("SELECT * FROM gen_code WHERE id=".$this->cnx->lastInsertId());
        }else{
            $this->cache['codes'][$gen]=$rsCode;
        }
        return $this->cache['codes'][$gen];
    }


    /**
     * Récupère les class 
     *
     * @param array     $c
     *
    */
	public function getIdCpt($cpt, $idsDico){
        //vérifie si la classe est déjà en cache
        if(isset($this->cache['concepts'][$cpt.'-'.$idsDico])){
            return $this->cache['concepts'][$cpt.'-'.$idsDico];
        }
        $query = 'SELECT r.id FROM resource r 
            INNER JOIN value vDico on vDico.resource_id = r.id AND vDico.property_id = 501 AND vDico.value_resource_id IN ('.$idsDico.') 
            INNER JOIN value v on v.resource_id = r.id AND v.property_id = 1 AND v.value = ? 
            WHERE r.resource_class_id = 106';
        $rs = $this->cnx->fetchAssoc($query,[$cpt]);
        if(!$rs){
            $this->logger->err('Pas de concept trouvé : '.$cpt.' - '.$idsDico);
            $this->cache['concepts'][$cpt.'-'.$idsDico] = null;
        }else
            $this->cache['concepts'][$cpt.'-'.$idsDico]= $rs['id'];
        return $this->cache['concepts'][$cpt.'-'.$idsDico];
	}


    /**
     * récupère un déterminant 
     *
     * @param string $det
     * 
     * @return boolean
     *
    */
	public function getIdDeterminant($det,$idsDico){

        if(!$det)return;

        //vérifie si le déterminant est déjà en cache
        if(isset($this->cache['determinants'][$det.'-'.$idsDico])){
            return $this->cache['determinants'][$det.'-'.$idsDico];
        }

        //vérifie si le vecteur est transmis
        if(substr($det,0,1)=='=')$this->cache['determinants'][$det.'-'.$idsDico]=null;

        //vérifie si le déterminant est pour un verbe
        if(strlen($det) > 6)return;

        //vérifie si le déterminant est un syntagme
        if(substr($det,-1)=='#'){
            $query = 'SELECT r.id FROM resource r 
                INNER JOIN value vDico on vDico.resource_id = r.id AND vDico.property_id = 501 AND vDico.value_resource_id IN ('.$idsDico.') 
                INNER JOIN value v on v.resource_id = r.id AND v.property_id = 10 AND v.value = ? 
                WHERE r.resource_class_id = 114';
            $rs = $this->cnx->fetchAssoc($query,[substr($det,0,-1)]);
            $this->cache['determinants'][$det.'-'.$idsDico] = $rs['id'];
            return $this->cache['determinants'][$det.'-'.$idsDico];
        };

        //vérifie s'il faut chercher le pluriel
        $intDet = intval($det);
        if($intDet >= 50){
        	$det = $intDet-50;
        }       			
        //vérifie s'il faut chercher le déterminant
        if($det!=0){
            $query = 'SELECT r.id FROM resource r 
                INNER JOIN value vDico on vDico.resource_id = r.id AND vDico.property_id = 501 AND vDico.value_resource_id IN ('.$idsDico.') 
                INNER JOIN value v on v.resource_id = r.id AND v.property_id = 10 AND v.value = ? 
                WHERE r.resource_class_id = 113';
            $rs = $this->cnx->fetchAssoc($query,[$det]);
            $this->cache['determinants'][$det.'-'.$idsDico] = $rs['id'];
        }
        return $this->cache['determinants'][$det.'-'.$idsDico];
    }



    /**
     * récupère les oeuvre liées à un concept
     *
     * @param   integer    $idCpt l'identifiant du concept
     * @return array
     */
    function getConceptsOeuvres($idCpt){

        //vérifie si les oeuvres sont déjà en cache
        if(isset($this->cache['oeuvresCpt'][$idCpt])){
            return $this->cache['oeuvresCpt'][$idCpt];
        }
        
        $query="SELECT 
                GROUP_CONCAT(DISTINCT vOeuvreDicos.value_resource_id) idsDico,
                oeuvre.id idOeuvre
            FROM
                resource r
                    INNER JOIN
                value vDico ON vDico.resource_id = r.id
                    AND vDico.property_id = 501
                    INNER JOIN
                value vOeuvre ON vOeuvre.value_resource_id = vDico.value_resource_id
                    AND vOeuvre.property_id = 501
                    INNER JOIN
                resource oeuvre ON oeuvre.id = vOeuvre.resource_id
                    AND oeuvre.resource_class_id = 409
                    INNER JOIN
                value vOeuvreDicos ON vOeuvreDicos.resource_id = oeuvre.id
                    AND vOeuvreDicos.property_id = 501
            WHERE
                r.id = ?
            GROUP BY oeuvre.id";
        $this->cache['oeuvresCpt'][$idCpt] = $this->cnx->fetchAll($query,[$idCpt]);
        if(count($this->cache['oeuvresCpt'][$idCpt])==0){
            $this->logger->err('Pas d\'oeuvre associée au concept : '.$idCpt);
        }
        
        return $this->cache['oeuvresCpt'][$idCpt];
    }

    /**
     * récupère les liens du concept dans la base de données ancienne
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function getOldConceptLinks($params){
        //pour chaque type de lien possible
        $rs = ["generateurs"=>[],'adjectifs'=>[],'verbes'=>[],'substantifs'=>[]];
        //recherche les liens avec les concepts
        $ids=implode(",",$params['ids']);
        $query="SELECT elision, prefix, m_s, f_s, m_p, f_p, group_concat(id_concept) idsCpt, group_concat(id_adj) idsAdj  
            FROM generateur.gen_adjectifs    
            WHERE id_concept IN (".$ids.")
            GROUP BY elision, prefix, m_s, f_s, m_p, f_p";
        $rs["adjectifs"] = $this->cnx->fetchAll($query);
        $query="SELECT DISTINCT elision, prefix, genre, s, p, group_concat(id_concept) idsCpt, group_concat(id_sub) idsSub  
            FROM generateur.gen_substantifs   
            WHERE id_concept IN (".$ids.")
            GROUP BY elision, prefix, genre, s, p";   
        $rs["substantifs"] = $this->cnx->fetchAll($query);
        $query="SELECT DISTINCT id_conj, elision, prefix, group_concat(id_concept) idsCpt, group_concat(id_verbe) idsVerbe  
            FROM generateur.gen_verbes   
            WHERE id_concept IN (".$ids.")
            GROUP BY id_conj, elision, prefix";   
        $rs["verbes"] = $this->cnx->fetchAll($query);
        $query="SELECT DISTINCT valeur, group_concat(id_concept) idsCpt, group_concat(id_gen) idsGen  
            FROM generateur.gen_generateurs   
            WHERE id_concept IN (".$ids.")
            GROUP BY valeur";
        $rs["generateurs"] = $this->cnx->fetchAll($query);
        return $rs;        
    }

    /**
     * récupère les doublons de concept dans la base de données ancienne
     *
     * @param   int    $limit le nombre de doublons à récupérer
     * @param   int    $offset le nombre de doublons à sauter
     * @return  array
     */
    function getOldConceptDoublons($cpt=null, $limit=null,$offset=null){
        $query="SELECT COUNT(*) nb, CONCAT(type,'_',lib) cpt, GROUP_CONCAT(id_concept) idsCpt, GROUP_CONCAT(id_dico) idsDicos
            FROM generateur.gen_concepts ";
        if($cpt!=null){
            $query.="WHERE CONCAT(type,'_',lib) = ? ";
        }
        $query.=" GROUP BY CONCAT(type,'_',lib)
            HAVING COUNT(*) > 1";
        if($limit!=null){
            $query.=" LIMIT ".$limit;
        }
        if($offset!=null){
            $query.=" OFFSET ".$offset;
        }
        if($cpt!=null){
            $rs = $this->cnx->fetchAll($query,[$cpt]);
        }else{
            $rs = $this->cnx->fetchAll($query);
        }
        return $rs;        
    }


    /**
     * récupère les concepts oublié dans la base de données ancienne
     *
     * @param   int    $limit le nombre de doublons à récupérer
     * @param   int    $offset le nombre de doublons à sauter
     * @return  array
     */
    function getOldConceptOubli($file){
        $result = [];
        if (($handle = fopen($file, "r")) !== false) {
            $data = [];
            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        foreach ($data as $d) {
            $type=null;
            $lib=null;  
            $p = strpos($d[0],"-");
            if($p!==false){
                $type = substr($d[0],0,$p);
                $lib = substr($d[0],$p+1);
            }else{
                $p = strpos($d[0],"_");
                if($p!==false){
                    $type = substr($d[0],0,$p);
                    $lib = substr($d[0],$p+1);
                }
            }

            if($type==null || $lib==null){
                $this->logger->err('Erreur dans le format du concept : '.$d[0]);
                continue;
            }

            $query="SELECT COUNT(*) nb, '".$d[0]."' cpt, GROUP_CONCAT(id_concept) idsCpt, GROUP_CONCAT(id_dico) idsDicos
            FROM generateur.gen_concepts 
            WHERE type = ? AND lib = ?";
            $rs = $this->cnx->fetchAll($query,[$type, $lib]);
            if($rs[0]['nb']==0){
                $this->logger->err('Pas de concept trouvé : '.$d[0]);
                continue;
            }
            $result[] = $rs[0];
        }
        return $result;        
    }


    /**
     * vérifie l'utilisation d'un concept dans la base de données ancienne
     *
     * @cpt     string    $cpt code du concept
     * @return  array
     */
    function getOldConceptUses($cpt){
        /*on ne met pas la liste des concepts car le liste est potentiellement trop longue
        cf. getOldConceptUsesListe pou ravoir la liste des concept qui utilise un concept
        $query='SELECT COUNT(g.id_gen) nb, GROUP_CONCAT(g.id_concept) idsCpt
       */
        $query='SELECT COUNT(g.id_gen) nb
            FROM generateur.gen_generateurs g
            WHERE g.valeur LIKE "%'.$cpt.'%"';
        $rs = $this->cnx->fetchAll($query);
        return $rs[0]['nb'];        
    }

    /**
     * récupère les stats d'utilisation des concepts dans la base de données ancienne
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function getStatsOldConceptUses($params){
        $rs=[];
        $cpts = isset($params['cpts']) ? $params['cpts'] : $this->getOldConceptDoublons($params['cpt'],$params['limit'],$params['offset']);
        foreach ($cpts as $i=>$v) {
            $v['uses']=$this->getOldConceptUses($v['cpt']);
            $v['links']=$this->getOldConceptLinks(['ids'=>explode(",",$v['idsCpt'])]);
            $rs[]=$v;
        }
        return $rs;        
    }


    /**
     * supprime un dictionnaire et les ressources associées
     *
     * @param   integer    $idDico paramètre de la requête
     * @return array
     */
    function deleteDico($idDico){
        $rs = $this->acl->userIsAllowed(null, 'create');
        if (!$rs) {
            $item = $this->api->read('items', $idDico)->getContent();
            return [
                'error' => 'droits insuffisants',
                'message' => "Vous n’avez pas le droit d’exécuter cette fonction.",
                'link' => $item->adminUrl('edit')
            ];
        }        


        set_time_limit(0);
        $rs=[];
        //récupère les resources uniquement associées à ce dico
        $query="SELECT 
                vDicoRes.resource_id id,
                GROUP_CONCAT(DISTINCT r.id) idsDico,
                COUNT(DISTINCT r.id) nb
            FROM
                resource r
                    INNER JOIN
                value vDicoRes ON vDicoRes.value_resource_id = r.id
                    AND vDicoRes.property_id = 501
            GROUP BY vDicoRes.resource_id
            HAVING GROUP_CONCAT(DISTINCT r.id) = ? AND nb = 1";
        $rsRes = $this->cnx->fetchAll($query,[$idDico]);
        //récupère les resources uniquement associés à cette resource
        foreach ($rsRes as $r) {
            $this->deleteResource($r['id']);
        }
        $this->deleteResource($idDico);
        $this->logger->info('Le dictionnaire '.$idDico.' est supprimée.');
        return [
            'message' => "Le dictionnaire ".$idDico." est supprimée.",
            'status' => 'ok',
        ];
    }

    /**
     * supprime une oeuvre et les dictionnaires associés
     *
     * @param   integer    $idOeuvre paramètre de la requête
     * @return array
     */
    function deleteOeuvre($idOeuvre){
        $rs = $this->acl->userIsAllowed(null, 'create');
        if (!$rs) {
            $item = $this->api->read('items', $idOeuvre)->getContent();
            return [
                'error' => 'droits insuffisants',
                'message' => "Vous n’avez pas le droit d’exécuter cette fonction.",
                'link' => $item->adminUrl('edit')
            ];
        }        

        set_time_limit(0);
        $rs=[];
        //récupère les dictionnaires uniquement associés à l'oeuvre
        //on ne supprime pas les dictionnaires généraux
        $query="SELECT 
            vOeuvreDicos.value_resource_id idDico,
            GROUP_CONCAT(DISTINCT r.id) idsOeuvre,
            COUNT(DISTINCT r.id) nb,
            vDicoType.value type
        FROM
            resource r
                INNER JOIN value vOeuvreDicos ON vOeuvreDicos.resource_id = r.id AND vOeuvreDicos.property_id = 501
                INNER JOIN value vDicoType ON vDicoType.resource_id = vOeuvreDicos.value_resource_id AND vDicoType.property_id = 196
                INNER JOIN value vDicoNonGen on vDicoNonGen.resource_id = vOeuvreDicos.value_resource_id and vDicoNonGen.property_id = 508 and vDicoNonGen.value = 'non'
        WHERE
            r.resource_class_id = 409
        GROUP BY vOeuvreDicos.value_resource_id
        HAVING GROUP_CONCAT(DISTINCT r.id) = ? AND nb = 1 ";
        $rsDico = $this->cnx->fetchAll($query,[$idOeuvre]);
        foreach ($rsDico as $i => $d) {
            //récupère les resources uniquement associées à ce dico
            $query="SELECT 
                    vDicoRes.resource_id id,
                    GROUP_CONCAT(DISTINCT r.id) idsDico,
                    COUNT(DISTINCT r.id) nb
                FROM
                    resource r
                        INNER JOIN
                    value vDicoRes ON vDicoRes.value_resource_id = r.id
                        AND vDicoRes.property_id = 501
                GROUP BY vDicoRes.resource_id
                HAVING GROUP_CONCAT(DISTINCT r.id) = ? AND nb = 1";
            $rsRes = $this->cnx->fetchAll($query,[$d['idDico']]);
            //récupère les resources uniquement associés à cette resource
            foreach ($rsRes as $r) {
                $this->deleteResource($r['id']);
            }
            $this->deleteResource($d['idDico']);
            $this->logger->info('Le dictionnaire '.$d['idDico'].' est supprimée.');

        }
        //supprime les ressources de l'oeuvre
        $this->deleteResource($idOeuvre);
        $this->logger->info("L'oeuvre ".$idOeuvre." est supprimée.");
        return [
            'message' => "L'oeuvre ".$idOeuvre." est supprimée.",
            'status' => 'ok',
        ];
    }

    function deleteResource($id){

        $rs = $this->acl->userIsAllowed(null, 'create');
        if (!$rs) {
            $item = $this->api->read('items', $id)->getContent();
            return [
                'error' => 'droits insuffisants',
                'message' => "Vous n’avez pas le droit d’exécuter cette fonction.",
                'link' => $item->adminUrl('edit')
            ];
        }        
        //supprime les resources uniquement associés à cette resource
        $query="SELECT 
                r.id,
                GROUP_CONCAT(DISTINCT vRes.value_resource_id) idsRes,
                COUNT(DISTINCT vRes.value_resource_id) nb
            FROM
                resource r
                    INNER JOIN value vRes ON vRes.resource_id = r.id
            GROUP BY r.id
            HAVING idsRes = ? AND nb = 1";
        $rs = $this->cnx->fetchAll($query,[$id]);
        foreach ($rs as $r) {
            //supprime les resources associés
            $this->deleteResource($r["id"]);
        }
        $query = "DELETE FROM value WHERE resource_id = ?";
        $this->cnx->executeQuery($query,[$id]);
        $query = "DELETE FROM resource WHERE id = ?";
        $this->cnx->executeQuery($query,[$id]);
        $delete = "DELETE FROM gen_flux WHERE ordre_id IN (SELECT id FROM gen_ordre WHERE term_id = ?)";
        $this->cnx->executeQuery($delete,[$id]);
        $query = "DELETE FROM gen_flux WHERE concept_id = ?";
        $this->cnx->executeQuery($query,[$id]);
        $delete = "DELETE FROM gen_ordre WHERE term_id = ?";
        $this->cnx->executeQuery($delete,[$id]);
        $query = "DELETE FROM gen_oeuvre_dico_concept_term WHERE oeuvre_id = ? OR dico_id = ? OR concept_id = ? OR term_id = ?";
        $this->cnx->executeQuery($query,[$id,$id,$id,$id]);

        $this->logger->info('La ressource '.$id.' est supprimée.');
        return [
            'message' => "La ressource ".$id." est supprimée.",
            'status' => 'ok',
        ];
    }

}

/*extraction des partie dures d'un générateur
SELECT v.value, REGEXP_SUBSTR(v.value, '\\[(.*?)\\]') AS extracted_text
, REGEXP_REPLACE(v.value, '\\[(.*?)\\]','') AS textDur
FROM resource r
inner join value v ON v.resource_id = r.id AND v.property_id = 187
WHERE r.resource_class_id = 107 

/*récupère les erreurs lors d'une explosion des concepts
SELECT distinct message FROM `log` WHERE `message` LIKE '%Pas d\'oeuvre associée au concept :%';


/*Pour enregistrer les relation entre les concepts et les termes
TRUNCATE TABLE gen_oeuvre_dico_concept_term;
ALTER TABLE gen_oeuvre_dico_concept_term  AUTO_INCREMENT = 1;
INSERT INTO gen_oeuvre_dico_concept_term(term_id, concept_id, dico_id, oeuvre_id)
SELECT 
	r.id idTerm,
	vCpt.value_resource_id idCpt,
    vDico.value_resource_id idDico,
    vOeu.resource_id idOeu
FROM resource r
	INNER JOIN value vCpt ON vCpt.resource_id = r.id AND vCpt.property_id = 189
	INNER JOIN value vDico ON vDico.resource_id = vCpt.value_resource_id AND vDico.property_id = 501
	INNER JOIN value vOeu ON vOeu.value_resource_id = vDico.value_resource_id AND vOeu.property_id = 501
    INNER JOIN resource rOeu ON rOeu.id = vOeu.resource_id AND rOeu.resource_class_id = 409
WHERE
	r.resource_class_id = 107;
*/

/*STATISTIQUES de création des resources
SELECT DATE_FORMAT(dur.creaItem0, '%j %k %i') dt, SUM(dur.tempsTotal) temps, COUNT(idItem)
FROM (SELECT 
	l0.id idParam, l0.created creaItem0, 
    l1.id idItem, l1.created creaItem1,
    TIMEDIFF (l1.created, l0.created) tempsTotal
    FROM resource l0 
    inner join resource l1 on l1.id = (l0.id+1) 
ORDER BY `tempsTotal` DESC) dur
GROUP BY DATE_FORMAT(creaItem0, '%j %k %i') 
ORDER BY `dt` DESC
*/


/*Requete pour extraire les terminaisons
SELECT 
    c.id_dico, c.modele, c.id_conj 
, t0.num id0, t0.lib present1
, t1.num id1, t1.lib present2
, t2.num id2, t2.lib present3
, t3.num id3, t3.lib present4
, t4.num id4, t4.lib present5
, t5.num id5, t5.lib present6
, t6.num id6, t6.lib imparfait1
, t7.num id7, t7.lib imparfait2
, t8.num id8, t8.lib imparfait3
, t9.num id9, t9.lib imparfait4
, t10.num id10, t10.lib imparfait5
, t11.num id11, t11.lib imparfait6
, t12.num id12, t12.lib passeSimple1
, t13.num id13, t13.lib passeSimple2
, t14.num id14, t14.lib passeSimple3
, t15.num id15, t15.lib passeSimple4
, t16.num id16, t16.lib passeSimple5
, t17.num id17, t17.lib passeSimple6
, t18.num id18, t18.lib futurSimple1
, t19.num id19, t19.lib futurSimple2
, t20.num id20, t20.lib futurSimple3
, t21.num id21, t21.lib futurSimple4
, t22.num id22, t22.lib futurSimple5
, t23.num id23, t23.lib futurSimple6
, t24.num id24, t24.lib conditionnel1
, t25.num id25, t25.lib conditionnel2
, t26.num id26, t26.lib conditionnel3
, t27.num id27, t27.lib conditionnel4
, t28.num id28, t28.lib conditionnel5
, t29.num id29, t29.lib conditionnel6
, t30.num id30, t30.lib subjonctif1
, t31.num id31, t31.lib subjonctif2
, t32.num id32, t32.lib subjonctif3
, t33.num id33, t33.lib subjonctif4
, t34.num id34, t34.lib subjonctif5
, t35.num id35, t35.lib subjonctif6
, t36.num id36, t36.lib participe
, t37.num id37, t37.lib infinitif
FROM
    gen_conjugaisons c
INNER JOIN gen_terminaisons t0 ON t0.id_conj = c.id_conj AND t0.num = 0
INNER JOIN gen_terminaisons t1 ON t1.id_conj = c.id_conj AND t1.num = 1
INNER JOIN gen_terminaisons t2 ON t2.id_conj = c.id_conj AND t2.num = 2
INNER JOIN gen_terminaisons t3 ON t3.id_conj = c.id_conj AND t3.num = 3
INNER JOIN gen_terminaisons t4 ON t4.id_conj = c.id_conj AND t4.num = 4
INNER JOIN gen_terminaisons t5 ON t5.id_conj = c.id_conj AND t5.num = 5
INNER JOIN gen_terminaisons t6 ON t6.id_conj = c.id_conj AND t6.num = 6
INNER JOIN gen_terminaisons t7 ON t7.id_conj = c.id_conj AND t7.num = 7
INNER JOIN gen_terminaisons t8 ON t8.id_conj = c.id_conj AND t8.num = 8
INNER JOIN gen_terminaisons t9 ON t9.id_conj = c.id_conj AND t9.num = 9
INNER JOIN gen_terminaisons t10 ON t10.id_conj = c.id_conj AND t10.num = 10
INNER JOIN gen_terminaisons t11 ON t11.id_conj = c.id_conj AND t11.num = 11
INNER JOIN gen_terminaisons t12 ON t12.id_conj = c.id_conj AND t12.num = 12
INNER JOIN gen_terminaisons t13 ON t13.id_conj = c.id_conj AND t13.num = 13
INNER JOIN gen_terminaisons t14 ON t14.id_conj = c.id_conj AND t14.num = 14
INNER JOIN gen_terminaisons t15 ON t15.id_conj = c.id_conj AND t15.num = 15
INNER JOIN gen_terminaisons t16 ON t16.id_conj = c.id_conj AND t16.num = 16
INNER JOIN gen_terminaisons t17 ON t17.id_conj = c.id_conj AND t17.num = 17
INNER JOIN gen_terminaisons t18 ON t18.id_conj = c.id_conj AND t18.num = 18
INNER JOIN gen_terminaisons t19 ON t19.id_conj = c.id_conj AND t19.num = 19
INNER JOIN gen_terminaisons t20 ON t20.id_conj = c.id_conj AND t20.num = 20
INNER JOIN gen_terminaisons t21 ON t21.id_conj = c.id_conj AND t21.num = 21
INNER JOIN gen_terminaisons t22 ON t22.id_conj = c.id_conj AND t22.num = 22
INNER JOIN gen_terminaisons t23 ON t23.id_conj = c.id_conj AND t23.num = 23
INNER JOIN gen_terminaisons t24 ON t24.id_conj = c.id_conj AND t24.num = 24
INNER JOIN gen_terminaisons t25 ON t25.id_conj = c.id_conj AND t25.num = 25
INNER JOIN gen_terminaisons t26 ON t26.id_conj = c.id_conj AND t26.num = 26
INNER JOIN gen_terminaisons t27 ON t27.id_conj = c.id_conj AND t27.num = 27
INNER JOIN gen_terminaisons t28 ON t28.id_conj = c.id_conj AND t28.num = 28
INNER JOIN gen_terminaisons t29 ON t29.id_conj = c.id_conj AND t29.num = 29
INNER JOIN gen_terminaisons t30 ON t30.id_conj = c.id_conj AND t30.num = 30
INNER JOIN gen_terminaisons t31 ON t31.id_conj = c.id_conj AND t31.num = 31
INNER JOIN gen_terminaisons t32 ON t32.id_conj = c.id_conj AND t32.num = 32
INNER JOIN gen_terminaisons t33 ON t33.id_conj = c.id_conj AND t33.num = 33
INNER JOIN gen_terminaisons t34 ON t34.id_conj = c.id_conj AND t34.num = 34
INNER JOIN gen_terminaisons t35 ON t35.id_conj = c.id_conj AND t35.num = 35
INNER JOIN gen_terminaisons t36 ON t36.id_conj = c.id_conj AND t36.num = 36
INNER JOIN gen_terminaisons t37 ON t37.id_conj = c.id_conj AND t37.num = 37
WHERE
    c.id_dico IN (4 , 14,
        16,
        34,
        38,
        39,
        40,
        41,
        42,
        44,
        46,
        67,
        68,
        69,
        70,
        73,
        82,
        93,
        94,
        96,
        99,
        101,
        102,
        112,
        118,
        122,
        123,
        124,
        129,
        130,
        132,
        134,
        135,
        137,
        140,
        141,
        142,
        144,
        145,
        146,
        147,
        148,
        149,
        150,
        155,
        163)
    */


    /*Vérification des terms sans dico
    SELECT count(*) nb, 
vHasDico.value_resource_id, rDico.title	
from resource r
inner join value vHasDico on vHasDico.resource_id = r.id and vHasDico.property_id = 501
inner join resource rDico on rDico.id = vHasDico.value_resource_id 
group by vHasDico.value_resource_id
*/