<?php
namespace Generateur\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Generateur\Generateur\Moteur;

class GenerateurSql extends AbstractHelper
{
    protected $api;
    protected $conn;
    protected $logger;
    protected $cache;
    /* plus nécessaire car la base est nettoyée
    var $idsDicosPropres = [4 , 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163];
    */
    public function __construct($api, $conn, $logger)
    {
        $this->logger = $logger;
        $this->api = $api;
        $this->conn = $conn;
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
            case 'explodeGenerateurs':
                $result = $this->explodeGenerateurs($params);
                break;
            case 'getOldConceptOubli':
                $result = $this->getOldConceptOubli("http://localhost/omk_generateur/modules/Generateur/data/imports/gen_conceptsOubli.csv");
                break;                    
                                                                    
        }                       

        return $result;

    }

    /**
     * décompose un générateur en ses composants
     *
     * @param array    $params paramètre de la requête
     * @return array
     */
    function explodeGenerateurs($params){
        set_time_limit(0);
        $this->cache = [];
        $this->logger->info(
            'Décomposition des générateurs : {idGen} - {limit} - {offset}.', // @translate
            $params
        );
        //création du moteur
        $m = new Moteur(false,$this->api,$this->logger);

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
        WHERE
            r.resource_class_id = 107 ";
        if($params['idGen']!=null){
            $query.=" AND r.id = ?";
        }   
        $query.=" GROUP BY r.id ";
        if($params['limit']!=null){
            $query.=" LIMIT ".$params['limit'];
        }
        if($params['offset']!=null){
            $query.=" OFFSET ".$params['offset'];
        }
        if($params['idGen']!=null){
            $rs = $this->conn->fetchAll($query,[$params['idGen']]);
        }else{
            $rs = $this->conn->fetchAll($query);
        }
        foreach ($rs as $i => $v) {
            //if($i<9294)continue;
            $this->logger->info(
                'Traitement du term : {idTerm} = {value} ({idsCpt}).', // @translate
                $v
            );

            //supprime les enregistrements pour ce term
            $delete = "DELETE FROM gen_ordre WHERE term_id = ?";
            $this->conn->executeQuery($delete,[$v['idTerm']]);
            $delete = "DELETE FROM gen_concept_terms WHERE term_id = ?";
            $this->conn->executeQuery($delete,[$v['idTerm']]);
            //$this->logger->info('Terme supprimé'.' : '.$v['idTerm']);

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
                //décompose le générateur
                $compo = $m->getGenCompo($g[0]);
                /*on enregistre la décomposition pour chaque concept et chaque oeuvre 
                car un concept est lié à un dictionnaire lui même en rapport avec des oeuvres ayant leurs propres dictionnaires
                la recherche des valeurs ce fait dans les dictionnaires associés aux oeuvres
                */
                $idsCpt = explode(",",$v['idsCpt']);
                foreach ($idsCpt as $idCpt) {
                    //Récupère les oeuvres associées au concept pour avoir les dictionnaires de références
                    $oeuvres = $this->getConceptsOeuvres($idCpt);
                    if(count($oeuvres)==0){
                        $this->logger->err('Pas d\'oeuvre associée au concept : '.$idCpt);
                        continue;
                    }
                    foreach ($oeuvres as $o) {
                        //construction des valeurs
                        $params = [
                            'oeuvre_id' => $o['idOeuvre'],
                            'concept_id' => $idCpt,
                            'term_id' => $v['idTerm'],
                            'value' => $g[0],
                            'ordre' => $g[1],
                            'conditionnel' => $g[1] > $posCondiDeb && $g[1] < $posCondiFin ? 1 : 0
                        ];
                        $idDet = false;
                        $params['determinant'] = isset($compo['det']) ? $compo['det'] : null;
                        $params['determinant_id'] = isset($compo['det']) ? $this->getIdDeterminant($compo['det'],$o['idsDico']) : null;
                        if($compo['class']){
                            $idsClass = $this->getIdClass($compo['class'], $o['idsDico']);
                            $params['gen_ids'] = implode(",",$idsClass);
                        }else {
                            $params['gen_ids'] = '';
                        } 
                        $insert = "INSERT INTO gen_flux (oeuvre_id, concept_id, term_id, value, ordre, conditionnel, determinant, determinant_id, gen_ids) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $this->conn->executeQuery($insert,[$params['oeuvre_id'],$params['concept_id'],$params['term_id'],$params['value'],$params['ordre']
                            ,$params['conditionnel'] ,$params['determinant'],$params['determinant_id'],$params['gen_ids']]);
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
                $insert = "INSERT INTO gen_flux (term_id, value, ordre, conditionnel) 
                    VALUES (?, ?, ?, ?)";
                $this->conn->executeQuery($insert,[$v['idTerm'],$d[0],$d[1],$d[1] > $posCondiDeb && $d[1] < $posCondiFin ? 1 : 0]);
            }
            $this->logger->info('Fin Traitement du term : '.$i);
        }
        return [
            'total' => count($rs),
            'limit' => $params['limit'],
            'offset' => $params['offset'],
        ];      

    }


    /**
     * Récupère les class 
     *
     * @param array     $c
     *
    */
	public function getIdClass($cls, $idsDico){
        $this->cache['classes'] = isset($this->cache['classes']) ? $this->cache['classes'] : [];
        //vérifie si la classe est déjà en cache
        if(isset($this->cache['classes'][$cls.'-'.$idsDico])){
            return $this->cache['classes'][$cls.'-'.$idsDico];
        }
        $result = [];
		$arrPosi=explode("@", $cls);
        //calcul la chaine générative
        foreach ($arrPosi as $c) {
            if(isset($this->cache['classes'][$c.'-'.$idsDico])){
                $result[] = $this->cache['classes'][$c.'-'.$idsDico];
                continue;
            }
            $query = 'SELECT r.id FROM resource r 
                INNER JOIN value vDico on vDico.resource_id = r.id AND vDico.property_id = 501 AND vDico.value_resource_id IN ('.$idsDico.') 
                INNER JOIN value v on v.resource_id = r.id AND v.property_id = 1 AND v.value = ? 
                WHERE r.resource_class_id = 106';
            $rs = $this->conn->fetchAll($query,[$c]);
            if(count($rs)==0){
                $this->logger->err('Pas de concept trouvé : '.$c);
                $this->cache['classes'][$c.'-'.$idsDico] = false;
                continue;
            }
            $this->cache['classes'][$c.'-'.$idsDico]= $rs[0]['id'];
            $result[] = $rs[0]['id'];
        }
        $this->cache['classes'][$cls.'-'.$idsDico] = $result;
        return $result;
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

        $this->cache['determinants'] = isset($this->cache['determinants']) ? $this->cache['determinants'] : [];
        //vérifie si le déterminant est déjà en cache
        if(isset($this->cache['determinants'][$det.'-'.$idsDico])){
            return $this->cache['determinants'][$det.'-'.$idsDico];
        }

        //vérifie si le vecteur est transmis
        if(substr($det,0,1)=='=')$this->cache['determinants'][$det.'-'.$idsDico]=false;

        //vérifie si le déterminant est pour un verbe
        if(strlen($det) > 6)$this->cache['determinants'][$det.'-'.$idsDico]=false;
        
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
            $rs = $this->conn->fetchAll($query,[$det]);
            $this->cache['determinants'][$det.'-'.$idsDico] = $rs[0]['id'];
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
        return $this->conn->fetchAll($query,[$idCpt]);
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
        $rs["adjectifs"] = $this->conn->fetchAll($query);
        $query="SELECT DISTINCT elision, prefix, genre, s, p, group_concat(id_concept) idsCpt, group_concat(id_sub) idsSub  
            FROM generateur.gen_substantifs   
            WHERE id_concept IN (".$ids.")
            GROUP BY elision, prefix, genre, s, p";   
        $rs["substantifs"] = $this->conn->fetchAll($query);
        $query="SELECT DISTINCT id_conj, elision, prefix, group_concat(id_concept) idsCpt, group_concat(id_verbe) idsVerbe  
            FROM generateur.gen_verbes   
            WHERE id_concept IN (".$ids.")
            GROUP BY id_conj, elision, prefix";   
        $rs["verbes"] = $this->conn->fetchAll($query);
        $query="SELECT DISTINCT valeur, group_concat(id_concept) idsCpt, group_concat(id_gen) idsGen  
            FROM generateur.gen_generateurs   
            WHERE id_concept IN (".$ids.")
            GROUP BY valeur";
        $rs["generateurs"] = $this->conn->fetchAll($query);
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
            $rs = $this->conn->fetchAll($query,[$cpt]);
        }else{
            $rs = $this->conn->fetchAll($query);
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
            $rs = $this->conn->fetchAll($query,[$type, $lib]);
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
        $rs = $this->conn->fetchAll($query);
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

}

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