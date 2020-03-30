<?php

require_once 'Model/Database.php';
require_once 'Model/CochonBase.php';
require_once 'Model/Photo.php';
require_once 'Model/PhotoBase.php';
require_once 'Model/LienCochonPhotoBase.php';
require_once 'Model/CouleurBase.php';
require_once 'Model/RaceBase.php';

class CochonController{

    /** function which manage the list of the pig */
    public static function displayListOfPig($tabGET){
        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        // recuperation de la liste des cochons 
        $cb = new CochonBase();

        // choix du sexe à afficher
        $sexeCochon = "tous"; 
        if (isset($tabGET['sexe'])) $sexeCochon = $tabGET['sexe'];
                
        // ordre à afficher
        $ordreCochon = "nom";
        if (isset($tabGET['ordre'])) $ordreCochon = $tabGET['ordre'];
        
        // sens
        $sens = "asc";
        if (isset($tabGET['sens'])) $sens = $tabGET['sens'];
        
        // pour pagination
        $nb_epp = 5; 
        if (isset($tabGET['nb_epp'])) $nb_epp = $tabGET['nb_epp'];

        // recup no_page
        $page = 0; 
        if (isset($tabGET['page'])) $page = $tabGET['page'];

        // Comptabilisation
        $nb_cochonnes = $cb->getCountWomen($o_conn);
        $nb_cochons = $cb->getCountMen($o_conn);
        $nb_total = $nb_cochons + $nb_cochonnes;
      
        // pour pagination
        $nb_pages = 1;
        if (($nb_total % $nb_epp ) == 0) 
        {
            $nb_pages = (int)($nb_total / $nb_epp); 
        }
        else{
            $nb_pages = (int)($nb_total / $nb_epp) +1;
        }
        

        $debut = 0;
        if ($page > 1){
            $debut = ($page - 1) * $nb_epp ;
        }

        
        $cochons = $cb->getCochonsActifs($o_conn, $sexeCochon, $ordreCochon, $sens, $nb_epp, $debut);
      
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
        $twig->addExtension(new Twig_Extensions_Extension_Date());
        echo $twig->render('admin/admin_pig-list.html.twig', ['cochons' => $cochons,
                                                             'session' => $_SESSION,
                                                             'nb_cochonnes' => $nb_cochonnes,
                                                             'nb_cochons' => $nb_cochons,
                                                             'sexe' => $sexeCochon,
                                                             'ordre' => $ordreCochon,
                                                             'sens' => $sens,
                                                             'nb_epp' => $nb_epp,
                                                             'nb_pages' => $nb_pages,
                                                             'page' => $page ]
                                                            );
    }    


    /**  manage the form for adding a pig in the database */
    public static function addUpdatePigForm($tabGET){
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();

        // get the list of the mothers
        $womens = CochonBase::getWomens($o_conn);
                
        // get the list of the fathers
        $mens = CochonBase::getMens($o_conn);

        // on recupère les races et couleurs
        $races = RaceBase::getRacesActives($o_conn);
        $couleurs = CouleurBase::getCouleursActives($o_conn);

        $id = -1;
        $myPig = [];
        $pictures = [];
        if (isset($tabGET['pig'])){
            $id = $tabGET['pig'];

            // get the details of the cochon
            // la connexion à la base
            $cb = new CochonBase();
            $myPig = $cb->getPig($o_conn, $id);

            // get the list of the pictures
            $data_pic = array(':coc_id' => $id);
            $pictures = PhotoBase::getPictures($o_conn, $data_pic);
        }

        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
        echo $twig->render('admin/admin_pig-add-update.html.twig', 
                                ['session' => $_SESSION,
                                'id' => $id,
                                'pig' => $myPig,
                                'womens' => $womens,
                                'mens' => $mens,
                                'pictures' => $pictures,
                                'races' => $races,
                                'couleurs' => $couleurs]);
    }

    //****************************************** */
    // ADD //
    /** manage the add of a pig in the database */
    public static function addPigBase($tabPost, $tabFile){
        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
       
        // ajout dans les tables
        // cochon
        $cb = new CochonBase();
        $id_cochon = CochonBase::getMaxId($o_conn)+1;
        $data_cochon = array(
            ':id' => $id_cochon,
            ':nom' => $tabPost['nom'],
            ':poids' => intval($tabPost['poids']),
            ':sexe' => $tabPost['sexe'],
            ':duree_vie' => $tabPost['duree_de_vie'],
            ':date_naiss' => $tabPost['date_naiss'], 
            ':description' => $tabPost['description'],          
            ':mere' => intval($tabPost['mere']),    
            ':pere' => intval($tabPost['pere']),
            ':couleur' => intval($tabPost['couleur']),
            ':race' => intval($tabPost['race'])            
        );
               
        $addCochon = $cb->addPig($o_conn, $data_cochon);
        
        // traitement pour les photos
        $pb = new PhotoBase();
        for ($cpt_photo = 1; $cpt_photo <= 5 ; $cpt_photo ++)
        {
            // trt pour chaque photo
            $id_photo = intval($pb->getMaxId($o_conn) +1);

            // pour le fichier
            if ($tabFile['picture_'.$cpt_photo]['type'] != "") 
            {
                if ($tabFile['picture_'.$cpt_photo]['type'] == "image/jpeg")        
                {
                    $extension = ".jpeg";
                }
                if ($tabFile['picture_'.$cpt_photo]['type'] =="image/png"){
                    $extension = ".png";
                }
    
                $pho_default = 0;
                if ($cpt_photo == 1){
                    $pho_default = 1;
                }

                $data_photo = array(
                    ':id' => $id_photo,
                    ':titre' => $tabPost['titre_' . $cpt_photo],
                    ':fichier' => $id_photo . $extension,
                    'default' => $pho_default,
                );


                $n_photo = $pb->addPicture($o_conn, $data_photo);
               
                 // lien
                $lcpb = new LienCochonPhotoBase();
                $id_lien = intval($lcpb->getMaxId($o_conn) +1);
                $data_lien = array(
                    ':id' => $id_lien,
                    ':coc_id' => $id_cochon,
                    ':pho_id' => $id_photo, 
                ); 
                $n_lien = $lcpb->addLink($o_conn, $data_lien);
                                    
                // traitement du fichier 
                Photo::savePhoto($tabFile, 'picture_'. $cpt_photo, $id_photo);
            }
           
        }

        $loader = new \Twig_Loader_FileSystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
     
        
        if ($addCochon == true)
        {
            // ok
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'add',
                            'value' => 'ok']);
        }
        else{
            // erreur
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'add']);
        }    
    }

    /** ask the confirmation for deleting a pig in the database */
    public static function askDeletePig($tabGET){
        $id = $tabGET['pig'];
        // geting the details
        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        $cb = new CochonBase();
        $p = $cb->getPig($o_conn,$id);
        $nom = $p[0]["coc_nom"];
       
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
      
        echo $twig->render('admin/admin_pig-ask-delete.html.twig', 
                            ['id' => $id,
                            'nom' => $nom]);
    }

    /** delete a pig from a database */
    public static function deletePig($tabGET){
        $id=$tabGET['id'];
        
        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        $cb = new CochonBase();

        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
            ]);

        if ($cb->deletePigBase($o_conn, $id) == 1)
        {
            // ok
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'delete',
                            'value' => 'ok']);
        }
        else{
            // erreur
            echo $twig->render('admin/admin_error.html.twig', 
                            ['type' => 'delete']);
        }
    }

    /** update of the database with the new information */
    public static function updatePig($tabPost,$tabFile){
        $data_pig = array(
            ':id' => $tabPost['id'],
            ':nom' => $tabPost['nom'],
            ':poids' => $tabPost['poids'],
            ':duree_vie' => $tabPost['duree_de_vie'],
            ':date_naiss' => $tabPost['date_naiss'],
            ':description' => $tabPost['description'],
            ':sexe' => $tabPost['sexe'],
            ':mere' => $tabPost['mere'],    
            ':pere' => $tabPost['pere'], 
            ':race' => $tabPost['race'],
            ':couleur' => $tabPost['couleur'],          
        );

        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();

        // for the pig
        $cb = new CochonBase();
        $updtPig = $cb->updatePig($o_conn, $data_pig);
        
        // for the pictures
        // get the data of the pictures
        $data_pic = array(':coc_id' => $tabPost['id']);
        $pict = PhotoBase::getPictures($o_conn, $data_pic);

        $pb = new PhotoBase();

        for ($cpt_photo = 1; $cpt_photo <= 5 ; $cpt_photo ++)
        {
            // on verifie si une fonction a été chargée
            if (isset($tabPost['id_' .$cpt_photo])){
                // trt pour chaque photo
                 $id_photo = $tabPost['id_' .$cpt_photo];

                // pour le fichier
                if ($tabFile['picture_'.$cpt_photo]['type'] != "") 
                {
                    if ($tabFile['picture_'.$cpt_photo]['type'] == "image/jpeg")        
                    {
                        $extension = ".jpeg";
                    }
                    if ($tabFile['picture_'.$cpt_photo]['type'] =="image/png"){
                        $extension = ".png";
                    }
        
                    $data_photo = array(
                        ':id' => $id_photo,
                        ':titre' => $tabPost['titre_' . $cpt_photo],
                        ':fichier' => $id_photo . $extension,
                    );
                    $u_photo = $pb->updatePicture($o_conn, $data_photo);
                    
                   
                    // traitement du fichier 
                    Photo::savePhoto($tabFile, 'picture_'. $cpt_photo, $id_photo);
                }
            }            
        }

        // envoi du rendu
        $loader = new \Twig_Loader_FileSystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
     
        if ($updtPig == true)
        {
            // ok
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'update',
                            'value' => 'ok']);
        }
        else{
            // erreur
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'update']);
        }
    }

    /** creation aléatoire de 10 cochons dans la base
     * on prend un tableau de prénom
     * on choisit ensuite les élements de façons aléatoires
     */
    public static function createRandomPigs(){
        // la connexion à la base
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        $cb = new CochonBase();

        $prenom_masculin = array(0=>"Albert", 1=>"Achille", 2=>"Adam", 3=>"Adolphe", 4=>"André", 
                                5=>"Alphonse", 6=>"Amadeus", 7=>"Aristide", 8=>"Armand", 9=>"Aurélien", 
                                10=>"Bernard",11=>"Bertrand", 12=>"Barthelemy", 13=>"Brian", 14=>"Benoît",
                                15=>"Boniface", 16=>"Boris", 17=>"Brandon", 18=>"Marc" , 19=>"Morgan" );


        $prenom_feminin = array(0=>"Adèle", 1=>"Adriana", 2=>"Agathe", 3=>"Agnès", 4=>"Agripine", 
                                5=>"Alice", 6=>"Alvina", 7=>"Ambre", 8=>"Annabelle", 9=>"Annaëlle", 
                                10=>"Apolline", 11=>"Ariane", 12=>"Armelle", 13=>"Astrid", 14=>"Barbara" ,
                                15=>"Béatrice", 16=>"Bélinda", 17=>"Bénédicte", 18=>"Bernadette", 19=>"Bess",
                                20=>"Betty",21=> "Blanche");
            
        $tabSexe = array(0 => "Femelle",
                        1 => "Mâle");

    
        $tabDescription = array(0 => "Naissance douloureuse, un peu fragile...à surveiller",
                                1 => "Un bon gros cochon, rien de spécial à signaler",
                                2 => "Un cochon dans la moyenne. Surveillance normale",
                                3 => "Un gros cochon né difficilement. Poids à surveiller");

        // genration des cochons             
        for ($cpt_cochons = 1; $cpt_cochons <= 10; $cpt_cochons ++){

            // trt for the id
            $id_cochon = CochonBase::getMaxId($o_conn)+1;

                $sexe = $tabSexe[random_int(0,count($tabSexe)-1)];
            if ($sexe == "Mâle"){
                $prenom = $prenom_masculin[ random_int(0,count($prenom_masculin) -1)] ;
            }
            else{
                $prenom = $prenom_feminin [ random_int(0,count($prenom_feminin) -1) ];
            }

            $description = $tabDescription[random_int(0, count($tabDescription)-1)];
            
            // generation de la date de naissance
            $annee = random_int(1960, 2019);
            $mois = random_int(1,12);
            $jour = random_int(1, 28);
            $heure = random_int(0,23);
            $min = random_int(0, 59);
            $sec = random_int(0, 59);
            $date_naissance = $annee . "-" . $mois . "-" . $jour . " " .$heure . ":" . $min . ":" . $sec;
            
            $mere = random_int( 0, count( $cb->getIdCochonnes($o_conn)) );
            $pere = random_int(0, count($cb->getIdCochons($o_conn)));
            
            $poids = random_int(250000, 360000);
            
            $duree_vie = random_int(15*365, 20*365);
            
            // add of the pig in the db
            $data = array(':id' => $id_cochon,
                        ':nom'=> $prenom,
                        ':poids'=>$poids,
                        ':sexe' => $sexe, 
                        ':duree_vie' => $duree_vie, 
                        ':date_naiss' => $date_naissance,
                        ':description' => $description, 
                        ":couleur" => 1,                    
                        ":race" => 1,
                        ':pere' =>$pere,
                        ':mere' => $mere);
            $retour = $cb->addPig($o_conn, $data);  
            
            // add of the photo
            $pb = new PhotoBase();
            $lcpb = new LienCochonPhotoBase();

            for ($i=0; $i<5; $i++){
                $id_photo = intval($pb->getMaxId($o_conn) +1);
                $fichier = "imageonline-co-placeholder-image_400.jpg";
                $titre = $i +1;
                $default = 0; 
                if ($i==0)
                {
                    $fichier = "imageonline-co-placeholder-image_750.jpg";
                    $titre = "Principale";
                    $default = 1;
                }
                $data_photo = array(
                    ':id' => $id_photo,
                    ':titre' => $titre,
                    ':fichier' => $fichier,
                    ':default' => $default,
                );
                $n_photo = $pb->addPicture($o_conn, $data_photo);

                // lien
               
                $id_lien = intval($lcpb->getMaxId($o_conn) +1);
                $data_lien = array(
                    ':id' => $id_lien,
                    ':coc_id' => $id_cochon,
                    ':pho_id' => $id_photo, 
                ); 
                $n_lien = $lcpb->addLink($o_conn, $data_lien);

            }                     
        }   
        
        // renvoi de la réponse
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
        if ($retour == 1){
            echo $twig->render('admin/admin_pig-result.html.twig', 
            ['type' => 'add-lot',
            'value' => 'ok']);
        } 
        else{
            // erreur
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'add-lot',
                            'value'=> 'erreur']);   
        }
    } 

    /** kill a pig : 
     * - keep it in the db
    */
    public static function killAPig($tabGET){
        $id_cochon = $tabGET['pig'];
        
        // pour ce faire, on va fixer la durée de vie 
        // à la durée entre sa date de naissance et maintenant
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        $cb = new CochonBase();    
        $data = array(':id' => $id_cochon);
        
        //var_dump($data);
        $kill = $cb->updateDdV($o_conn, $data);
        //var_dump($kill);

        // renvoi de la réponse
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
        if ($kill == 1){
            echo $twig->render('admin/admin_pig-result.html.twig', 
            ['type' => 'kill',
            'value' => 'ok']);
        } 
        else{
            // erreur
            echo $twig->render('admin/admin_pig-result.html.twig', 
                            ['type' => 'kill',
                            'value'=> 'erreur']);   
        }
    }


    /** displau the activ pigs */
    public static function displayActivPigs($tabPOST, $tabGET){
        $couleur = "tous";
        if (isset($_POST['couleur'])) $couleur = $_POST["couleur"];
        $race = "tous";
        if (isset($_POST['race'])) $race = $_POST["race"];

        // connexion
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();
        
        // on va chercher les cochons
        $cochons = CochonBase::getListeCochons($o_conn,$couleur, $race, 0, 0); 

        // renvoi de la réponse
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
             
        $twig->addExtension(new Twig_Extensions_Extension_Text());

        //var_dump($cochons);
        echo $twig->render('liste.html.twig', 
                ['cochons' => $cochons,
                'rubrique' => 'cochons']);
    }

    /** displau the details of a pig*/
    public static function displayDetailsPig($tabGET){
        
        $id = intval($tabGET['id']);

        // on va cherches les infos du cochon
        $o_pdo =  new Database();
        $o_conn = $o_pdo->makeConnect();

        
        $cochon = CochonBase::getPig($o_conn, $id)[0];
        $photos = PhotoBase::getPictures($o_conn, 
            array(':coc_id'=>$id)
        );

        // renvoi de la réponse
        $loader = new \Twig_Loader_Filesystem('View/templates');
        $twig = new \Twig_Environment($loader, [
            'cache' => false,
        ]);
        echo $twig->render('details.html.twig', 
                ['cochon' => $cochon,
                'photos' => $photos]);
    }
}