/*Pour avoir la liste des dico est le lien vers les oeuvres
*/
SELECT 
    COUNT(*) nb,
    GROUP_CONCAT(DISTINCT gd.id_dico) idsDico,
    gd.nom,
    gd.type,
    gd.langue,
    gd.general,
    gd.licence,
    GROUP_CONCAT(DISTINCT godu.id_oeu) idsOeu    
FROM
    gen_dicos gd
    left join gen_oeuvres_dicos_utis godu on godu.id_dico = gd.id_dico
GROUP BY gd.id_dico;

/*Pour avoir le nombre d'élÉments par dictionnaire
  - le nombre de concepts
  - le nombre de pronoms
  - le nombre de syntagmes
*/
SELECT 
    gd.id_dico,
    gd.type,
    COUNT(DISTINCT gc.id_concept) nbConcept,
    COUNT(DISTINCT gp.id_pronom) nbPronom,
    COUNT(DISTINCT gs.id_syn) nbSyntagme
FROM
    gen_dicos gd
    LEFT JOIN gen_concepts gc ON gc.id_dico = gd.id_dico
    LEFT JOIN gen_pronoms gp ON gp.id_dico = gd.id_dico
    LEFT JOIN gen_syntagmes gs ON gs.id_dico = gd.id_dico
GROUP BY gd.id_dico;
/*ON LE FAIT EN 2 FOIS POUR des RAISONS DE PERFORMANCES
Pour avoir le nombre d'élÉments par dictionnaire
  - le nombre de déterminants
  - le nombre de négations
*/
SELECT 
    gd.id_dico,
    gd.type,
    COUNT(DISTINCT gdet.id_dtm) nbDeterminant,
    COUNT(DISTINCT gn.id_negation) nbNegation,
    COUNT(DISTINCT gc.id_conj) nbConjugaison,
    GROUP_CONCAT(DISTINCT u.login) uti    
FROM
    gen_dicos gd
    LEFT JOIN gen_determinants gdet ON gdet.id_dico = gd.id_dico
    LEFT JOIN gen_negations gn ON gn.id_dico = gd.id_dico
    LEFT JOIN gen_conjugaisons gc ON gc.id_dico = gd.id_dico
    LEFT JOIN gen_oeuvres_dicos_utis godu on godu.id_dico = gd.id_dico
    LEFT JOIN flux_uti u on u.uti_id = godu.uti_id
GROUP BY gd.id_dico;

-- récupère les éléments des dictionnaires propres
-- pour les adjectifs
SELECT * FROM ( SELECT count(*) nb, GROUP_CONCAT(DISTINCT gc.id_dico), COUNT(DISTINCT gc.id_dico) nbDico, gc.type, gc.lib, COUNT(ga.id_adj) nbObj FROM gen_concepts gc inner join gen_adjectifs ga on ga.id_concept = gc.id_concept where gc.id_dico in (4, 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163) GROUP BY gc.type, gc.lib) details WHERE nbDico < nbObj AND nbDico > 1;

-- pour les pronoms
SELECT *
FROM gen_pronoms
where id_dico in (4, 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163);

-- pour les syntagmes
SELECT *
FROM gen_syntagmes
where id_dico in (4, 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163);

-- pour les concepts
SELECT *
FROM gen_concepts
where id_dico in (4, 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163);


-- pour les déterminants
SELECT d0.id_dico, d0.num, d0.ordre, d0.lib noEliMasSing  
, d1.lib noEliFemSing
, d2.lib EliMasSing
, d3.lib EliFemSing
, d4.lib noEliMasPlu
, d5.lib noEliFemPlu
, d6.lib EliMasPlu
, d7.lib EliFemPlu
FROM gen_determinants d0
inner join gen_determinants d1 on d1.id_dico = d0.id_dico and d1.num = d0.num and d1.ordre = 1
inner join gen_determinants d2 on d2.id_dico = d0.id_dico and d2.num = d0.num and d2.ordre = 2
inner join gen_determinants d3 on d3.id_dico = d0.id_dico and d3.num = d0.num and d3.ordre = 3
inner join gen_determinants d4 on d4.id_dico = d0.id_dico and d4.num = d0.num and d4.ordre = 4
inner join gen_determinants d5 on d5.id_dico = d0.id_dico and d5.num = d0.num and d5.ordre = 5
inner join gen_determinants d6 on d6.id_dico = d0.id_dico and d6.num = d0.num and d6.ordre = 6
inner join gen_determinants d7 on d7.id_dico = d0.id_dico and d7.num = d0.num and d7.ordre = 7
where d0.id_dico in (4, 14, 16, 34, 38, 39, 40, 41, 42, 44, 46, 67, 68, 69, 70, 73, 82, 93, 94, 96, 99, 101, 102, 112, 118, 122, 123, 124, 129, 130, 132, 134, 135, 137, 140, 141, 142, 144, 145, 146, 147, 148, 149, 150, 155, 163) AND d0.ordre = 0;

-- pour les gen_conjugaisons
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