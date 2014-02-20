<?php

namespace Acme\BiomedicalBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Acme\BiomedicalBundle\Entity\Definition;

class DefaultController extends Controller
{
    public function indexAction(){
        return $this->render('AcmeBiomedicalBundle:Default:index.html.twig',array('title'=>'BioMedicalSemantic'));
    }
    
    public function goToOntologieAction(){
    	$ontology=$_GET['searchOntology'];
    	$result=$this->getDoctrine()->getRepository("AcmeBiomedicalBundle:Ontology")
    	->findOneBy(array('name'=>$ontology));
    	return $this->render('AcmeBiomedicalBundle:Default:ontology.html.twig',array('title'=>'Ontology','ontology'=>$result));
    }
    
    public function goToTermAction(){
    	$term=$_GET['search'];
    	$term_result=$this->getDoctrine()->getRepository("AcmeBiomedicalBundle:Term")
    	->findOneBy(array('name'=>$term));
    	$definition=$this->getDoctrine()->getRepository("AcmeBiomedicalBundle:Definition")
    	->findOneBy(array('concept_id'=>$term_result->getConceptId()));
    	if (is_null($definition)){
    		return $this->render('AcmeBiomedicalBundle:Default:term.html.twig',array('title'=>'Term','term'=>$term_result));
    	}else{
    		return $this->render('AcmeBiomedicalBundle:Default:term.html.twig',array('title'=>'Term','term'=>$term_result,'definition'=>$definition->getDefinition()));
    	}
    }
    
    public function changeLanguageAction($langue=null){
    	if($langue != null){
    		// On enregistre la langue en session
    		$request = $this->getRequest();
    		$locale = $request->getLocale();
    		$request->setLocale($langue);
    		//$this->container->get('session')->set('locale', $langue);
    	}
    	
    	// on tente de rediriger vers la page d'origine
    	$url = $this->container->get('request')->headers->get('referer');
    	if(empty($url)) {
    		$url = $this->container->get('router')->generate('acme_biomedical_homepage');
    	}
    	return new RedirectResponse($url);
    }
    
    /**
     * Fonction permettant d'effectuer l'autocomplétion pour la page 
     * semantic_distance.html.twig et index.html.twig .
     * Permet de chercher tous les termes possibles.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showConceptsAction(){
        $request = $this->get('request');
         
        if($request->isXmlHttpRequest()){
            $term = $request->request->get('search');
            
            $quer = $this->getDoctrine()->getEntityManager();
            /*$query=$quer->createQuery("SELECT t.name as term,o.name as ontology
            		FROM AcmeBiomedicalBundle:Term t, AcmeBiomedicalBundle:Concept c,AcmeBiomedicalBundle:Ontology o
            		WHERE t.name LIKE ?1
            		AND t.concept_id=c.id
            		AND c.ontology_id=o.id
            		ORDER BY t.name ASC")
            ->setParameter(1,$term."%")
            ->setMaxResults(10);*/
            $query=$quer->createQueryBuilder()
            ->select("t.name as term")
            ->from("AcmeBiomedicalBundle:Term", "t")
            ->where("t.name LIKE ?1")
            ->orderBy("t.name","ASC")
            ->distinct(true)
            ->setParameter(1, $term."%")
            ->setMaxResults(10)
            ->getQuery();
            $recup = $query->getArrayResult();
            $concepts=array();
			foreach ( $recup as $data ) {
				//$concepts[] = $data ['term']." (".$data ['ontology'].")";
				$concepts[] = $data ['term'];
			}
			if (count($recup)==0){
				array_push($concepts, "Aucune proposition");
			}
            $response = new Response(json_encode($concepts));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
    }
    
    /**
     * Fonction permettant d'effectuer l'autocomplétion pour la page 
     * semantic_distance.html.twig . Etant donner un terme donné et
     * une ontologie.Ici on cherche une ontologie.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showOntologiesWithConceptAction(){
    	$request = $this->get('request');
    	 
    	if($request->isXmlHttpRequest()){
    		$term_ontology = $request->request->get('search');
    		$tab=split("/",$term_ontology);
    		$term=$tab[0];
    		$ontology=$tab[1];
    		
    		$quer = $this->getDoctrine()->getEntityManager();
    		$query=$quer->createQueryBuilder()
    		->select("o.name")
    		->from("AcmeBiomedicalBundle:Ontology", "o")
    		->from("AcmeBiomedicalBundle:Term", "t")
    		->from("AcmeBiomedicalBundle:Concept", "c")
    		->where("o.name LIKE ?1")
    		->andWhere("t.name=?2")
    		->andWhere("t.concept_id=c.id")
    		->andWhere("c.ontology_id=o.id")
    		->orderBy("o.name","ASC")
    		->distinct(true)
    		->setParameters(array(1=>"%".$ontology."%",2=>$term))
    		->setMaxResults(10)
    		->getQuery();
    		$recup = $query->getArrayResult();
    		$concepts=array();
    		foreach ( $recup as $data ) {
    			$concepts[] = $data ['name'];
    		}
    		if (count($recup)==0){
    			array_push($concepts, "Aucune proposition");
    		}
    		$response = new Response(json_encode($concepts));
    		$response->headers->set('Content-Type', 'application/json');
    		return $response;
    	}
    }
    
    /**
     * Fonction permettant d'effectuer l'autocomplétion pour la page
     * semantic_distance.html.twig . Etant donner un terme donné et
     * une ontologie.Ici on cherche un terme appartenant à une ontologie connue.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showConceptsWithOntologyAction(){
    	$request = $this->get('request');
    
    	if($request->isXmlHttpRequest()){
    		$term_ontology = $request->request->get('search');
    		$tab=split("/",$term_ontology);
    		$term=$tab[0];
    		$ontology=$tab[1];
    
    		$quer = $this->getDoctrine()->getEntityManager();
    		$query=$quer->createQueryBuilder()
    		->select("t.name")
    		->from("AcmeBiomedicalBundle:Ontology", "o")
    		->from("AcmeBiomedicalBundle:Term", "t")
    		->from("AcmeBiomedicalBundle:Concept", "c")
    		->where("o.name=?1")
    		->andWhere("t.name LIKE ?2")
    		->andWhere("t.concept_id=c.id")
    		->andWhere("c.ontology_id=o.id")
    		->orderBy("t.name","ASC")
    		->distinct(true)
    		->setParameters(array(1=>$ontology,2=>$term."%"))
    		->setMaxResults(10)
    		->getQuery();

    		$recup = $query->getArrayResult();
    		$concepts=array();
    		foreach ( $recup as $data ) {
    			$concepts[] = $data ['name'];
    		}
    		if (count($recup)==0){
    			array_push($concepts, "Aucune proposition");
    		}
    		$response = new Response(json_encode($concepts));
    		$response->headers->set('Content-Type', 'application/json');
    		return $response;
    	}
    }
    
    /**
     * Fonction permettant d'effectuer l'autocomplétion pour la page 
     * index.html.twig . Permet de chercher toutes les ontologies.
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showOntologiesAction(){
    	$request = $this->get('request');
    	
    	if($request->isXmlHttpRequest()){
    		$term = $request->request->get('searchOntology');
    		 
    		$quer = $this->getDoctrine()->getEntityManager();
    		$query=$quer->createQuery("SELECT o.name
            		FROM AcmeBiomedicalBundle:Ontology o
            		WHERE o.name LIKE ?1
    				ORDER BY o.name ASC")
    	    				->setParameter(1,$term."%")
    	    				->setMaxResults(10);
    		$recup = $query->getArrayResult();
    		$concepts=array();
    		foreach ( $recup as $data ) {
    			$concepts[] = $data ['name'];
    		}
    		if (count($recup)==0){
    			array_push($concepts, "Aucune proposition");
    		}
    		$response = new Response(json_encode($concepts));
    		$response->headers->set('Content-Type', 'application/json');
    		return $response;
    	}
    }
}
