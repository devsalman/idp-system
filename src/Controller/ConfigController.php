<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\KeyPairService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ConfigController extends AbstractController
{
    #[Route('/config', name: 'app_config')]
    public function index(KeyPairService $keyPairService): Response
    {
        return $this->render('config/index.html.twig', [
            'status' => $keyPairService->getStatus(),
            'secrets_dir' => $keyPairService->secretsDirectory(),
        ]);
    }

    #[Route('/config/generate-keys', name: 'app_config_generate_keys', methods: ['POST'])]
    public function generateKeys(Request $request, KeyPairService $keyPairService): Response
    {
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        if (!$this->isCsrfTokenValid('generate_keys', $csrfToken)) {
            $this->addFlash('error', 'Sesi tidak valid. Silakan coba lagi.');

            return $this->redirectToRoute('app_config');
        }

        try {
            $keyPairService->generate();

            $this->addFlash('success', sprintf(
                'Key pair ES256 dan did.json berhasil dibuat di %s.',
                $keyPairService->secretsDirectory(),
            ));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Gagal membuat key pair: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_config');
    }
}
