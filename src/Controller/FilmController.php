<?php
// src/Controller/HomePage.php
namespace App\Controller;

use App\Entity\Film;
use App\Tools\OmdbAPI;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FilmController extends AbstractController
{

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

            if(is_null($description)){
                return $this->redirectToRoute("erreur");
            }

            $film->setDescription($description);
            $film->setName(ucwords($film->getName()));
            $man = $doctrine->getManager();
            $man->persist($film);
            $man->flush() ;
            return $this->redirectToRoute('success');
        }

        return $this->renderForm('ajouter.html.twig', [
            'form' => $form
        ]);
    }

    public function displayFilms(ManagerRegistry $doctrine): Response
    {
        $repos = $doctrine->getRepository(Film::class);
        $films = $repos->findAll();

        return ($this->render('homepage.html.twig', ['films' => $films])
        );
    }

    public function success() : Response{
        return new Response(
            '<html><body>PARFAIT</body></html>'
        );
    }
}
