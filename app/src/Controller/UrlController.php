<?php

namespace App\Controller;

use App\Entity\Url;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UrlController extends AbstractController
{
    #[Route('/', name:'index')]
    public function index(Request $request): Response
    {
        $url = new Url();

        $form = $this->createFormBuilder($url)
            ->add('url', UrlType::class)
            ->add('keywords', TextType::class)
            ->add('save', SubmitType::class, ['label' => 'Check URL'])
            ->setMethod('POST')
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $url = $form->get('url')->getData();

            return $this->render('url/success.html.twig', [
                'url' => $url,
                'response' => $this->checkUrl($url, $form->get('keywords')->getData()),
            ]);
        }

        return $this->render('url/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/url-success', name:'task_success')]
    public function success(Request $request, $formData)
    {
        return $this->render('url/success.html.twig', [
            'formData' => $formData,
        ]);
    }

    public function checkUrl($url, $keywords = '', $counter = 0)
    {
        error_reporting(0);
        $foundKeywords = [];

        $response = $this->formRequest($url);

        if ($response['http_code'] == 301 || $response['http_code'] == 302 || $response['http_code'] == 303) {
            $headers = get_headers($response['url']);

            foreach ($headers as $value) {
                if (substr(strtolower($value), 0, 9) == "location:") {
                    $chain[] = $response['http_code'] . ' - ' . $value;
                    $counter++;

                    $this->checkUrl(trim(substr($value, 9, strlen($value))), 8, $counter);
                }
            }
        };

        if (($response['http_code'] !== 404)) {
            $current_page = file_get_contents($response['url']);

            foreach (explode(',', $keywords) as $keyword) {
                if (strpos($current_page, $keyword) !== false) {
                    $foundKeywords[] = $keyword;
                }
            }
        }

        return [
            'chain' => $chain,
            'counter' => $counter,
            'keywords' => $foundKeywords,
        ];
    }

    public function formRequest($url, $timeout = 5)
    {
        $ua = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0';

        $cookie = tempnam("/tmp", "CURLCOOKIE");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $ua);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $content = curl_exec($curl);

        curl_close($curl);

        return curl_getinfo($curl);
    }
}
