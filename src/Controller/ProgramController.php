<?php

namespace App\Controller;

use App\Entity\Episode;
use App\Entity\Program;
use App\Entity\Season;
use App\Form\ProgramType;
use App\Repository\ProgramRepository;
use App\Repository\SeasonRepository;
use App\Service\ProgramDuration;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Entity;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/program', name: 'program_')]
class ProgramController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ProgramRepository $programRepository): Response
    {
        $programs = $programRepository->findAll();
        return $this->render('program/index.html.twig', [
            'programs' => $programs,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(MailerInterface $mailer,Request $request, ProgramRepository $programRepository, SluggerInterface $slugger): Response
    {
        $program = new Program();
        $form = $this->createForm(ProgramType::class, $program);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($program->getTitle());
            $program->setSlug($slug);
            $programRepository->save($program, true);
            $this->addFlash('green', 'Une série a bien été ajoutée.');
            $email = (new Email())
                ->to('user@hotmail.fr')
                ->subject('new program added')
                ->text('A new program has been added to Wild series')
                ->html($this->renderView('program/ProgramEmail.html.twig', [
                    'program' => $program,
                ]));

            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                // some error prevented the email sending; display an
                // error message or try to resend the message
            }
            return $this->redirectToRoute('program_index', [], Response::HTTP_CREATED);
        }
        return $this->renderForm('program/new.html.twig', [
            'form' => $form
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(Program $program, SeasonRepository $seasonRepository, ProgramDuration $duration): Response
    {
        $seasons = $seasonRepository->findBy(['program' => $program]);
        return $this->render('program/show.html.twig', [
            'program' => $program,
            'seasons' => $seasons,
            'program_duration' => $duration->calculate($program),
        ]);
    }

    #[Route('/{slug}/season/{seasonId}', name: 'season_show', requirements: [ 'seasonId' => '\d+'])]
    #[ParamConverter("program", class: "App\Entity\Program", options: ['mapping' => ["slug" => "slug"]])]
    #[ParamConverter("season", class: "App\Entity\Season", options: ["mapping" => ["seasonId" => "id"]])]
    public function showSeason(Program $program, Season $season): Response
    {
        $episodes = $season->getEpisodes();
        return $this->render('program/season_show.html.twig', [
            'program' => $program,
            'season' => $season,
            'episodes' => $episodes,
        ]);
    }

    #[Route('/{slug}/season/{seasonId}/episode/{episode_slug}', name: 'episode_show', requirements: ['seasonId' => '\d+', 'episodeId' => '\d+'])]
    #[Entity('season', options: ['id' => 'seasonId'])]
    #[ParamConverter('episode', class: 'App\Entity\Episode', options: ['mapping' => ['episode_slug' => 'slug']])]
    public function showEpisode(Program $program, Season $season, Episode $episode): Response
    {
        return $this->render('program/episode_show.html.twig', [
            'program' => $program,
            'season' => $season,
            'episode' => $episode,
        ]);

    }

    #[Route('/{slug}/edit', name: "edit")]
    public function edit(Request $request, Program $program, ProgramRepository $programRepository, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(ProgramType::class, $program);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $slug = $slugger->slug($program->getTitle());
            $program->setSlug($slug);
            $programRepository->save($program, true);
            $this->addFlash('green', 'La série a bien été éditée.');

            return $this->redirectToRoute('program_show', ['slug'=>$program->getSlug()], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('program/edit.html.twig', [
            'form' => $form,
            'program' => $program,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST', 'DELETE'])]
    public function delete(Program $program, ProgramRepository $programRepository, Request $request): Response
    {
        $token = $request->get('_token');
        if (!is_string($token)) {
            throw new InvalidCsrfTokenException('error on the Csrf token');
        }
        if ($this->isCsrfTokenValid('delete'.$program->getId(), $token)) {
            $programRepository->remove($program, true);
            $this->addFlash('red', 'La série a été supprimé !');
        }
        return $this->redirectToRoute('program_index', [], Response::HTTP_SEE_OTHER);
    }
}
