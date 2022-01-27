<?php
// src/Controller/HomePage.php
namespace App\Controller;

use App\Entity\Film;
use App\Tools\OmdbAPI;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FilmController extends AbstractController
{

    public function massImport(Request $request, HttpClientInterface $client, ManagerRegistry $doctrine): Response
    {
        $defaultData = ['message' => 'default'];


        $form = $this->createFormBuilder($defaultData)
            ->add('fichier', FileType::class, ['mapped' => false])
            ->add('envoyer', SubmitType::class)
            ->getForm();


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get("fichier")->getData();
            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
            $file->move('imports/spreadsheets', $fileName);



            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");
            $spreadsheet = $reader->load('imports/spreadsheets/' . $fileName);
            $worksheet = $spreadsheet->getActiveSheet();

            $ajoutes = [];
            foreach ($worksheet->getRowIterator() as $row) {
                $val = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);
                $film = new Film();

                foreach ($cellIterator as $cell) {
                    array_push($val, $cell->getValue());
                }
                $film->setName($val[0]);
                $film->setScore($val[1]);
                $film->setVotersNumber($val[2]);

                $omdb = new OmdbAPI($client);
                $description = $omdb->getDescriptionByName($film->getName());

                if (!is_null($description)) {
                    array_push($ajoutes, $film->getName());

                    $film->setDescription($description);

                    $man = $doctrine->getManager();
                    $man->persist($film);
                    $man->flush();
                }
            }
            return $this->renderForm('success.html.twig');
        }


        return $this->renderForm('ajouterfichier.html.twig', [
            'form1' => $form,
        ]);
    }


    public function new(Request $request, HttpClientInterface $client, ManagerRegistry $doctrine): Response
    {
        // creates a task object and initializes some data for this example
        $film = new Film();
        $film->setName('');
        $film->setDescription('');
        $film->setScore(0);
        $film->setVotersNumber(0);

        $form = $this->createFormBuilder($film)
            ->add('name', TextType::class)
            ->add('score', NumberType::class)
            ->add('votersNumber', NumberType::class)
            ->add('email', EmailType::class, ['mapped' => false])
            ->add('save', SubmitType::class, ['label' => 'Envoyer'])
            ->setMethod('POST')
            ->getForm();


        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $film = $form->getData();
            $omdb = new OmdbAPI($client);
            $description = $omdb->getDescriptionByName($film->getName());

            if (is_null($description)) {
                return $this->redirectToRoute("error");
            }

            $film->setDescription($description);
            $film->setName(ucwords($film->getName()));
            $man = $doctrine->getManager();
            $man->persist($film);
            $man->flush();
            return $this->redirectToRoute("success");
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get("file")->getData();
            var_dump($file);
        }



        $form->handleRequest($request);

        return $this->renderForm('ajouter.html.twig', [
            'form1' => $form,
        ]);
    }

    public function displayFilms(ManagerRegistry $doctrine): Response
    {
        $repos = $doctrine->getRepository(Film::class);
        $films = $repos->findAll();

        return ($this->render('homepage.html.twig', ['films' => $films])
        );
    }

    public function success(): Response
    {
        return $this->render('success.html.twig');
    }

    public function error(): Response
    {
        return $this->render('error.html.twig');
    }

    public function delete(ManagerRegistry $doctrine): Response
    {


        if (isset($_GET["code"]) && isset($_GET["idfilm"])) {

            $code = $_GET["code"];
            $idFilm = intval($_GET["idfilm"]);
            $adminCode = $this->getParameter("admin_code");

            if ($adminCode === $code) {

                $repos = $doctrine->getRepository(Film::class);
                $film = $repos->findOneBy(["id" => $idFilm]);

                $man = $doctrine->getManager();
                $man->remove($film);
                $man->flush();

                return $this->render('deletesuccess.html.twig');
            }

            return $this->render('deleteerror.html.twig');
        }

        return $this->redirectToRoute("index");
    }
}
