<?php
declare(strict_types=1);

namespace App\Domain\Obra;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

final class SvgSanitizer
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const FORBIDDEN_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video',
        'canvas', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta',
        'image', 'feimage', 'a', 'animate', 'animatemotion', 'animatetransform',
        'set', 'cursor', 'font-face-uri',
    ];

    public static function sanitize(string $content): string
    {
        $content = trim($content);
        if ($content === '' || stripos($content, '<!DOCTYPE') !== false) {
            throw new InvalidArgumentException('O SVG está vazio ou contém uma declaração não permitida.');
        }
        if (!class_exists(DOMDocument::class)) {
            throw new InvalidArgumentException('O servidor não possui suporte para validar arquivos SVG.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML(
                $content,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
            );
            if (!$loaded || !$document->documentElement instanceof DOMElement) {
                throw new InvalidArgumentException('O arquivo SVG não contém XML válido.');
            }

            $root = $document->documentElement;
            if (strtolower($root->localName) !== 'svg'
                || !in_array((string) $root->namespaceURI, ['', self::SVG_NAMESPACE], true)) {
                throw new InvalidArgumentException('O arquivo informado não é um SVG válido.');
            }
            if ($document->doctype !== null) {
                throw new InvalidArgumentException('Declarações DOCTYPE não são permitidas em SVG.');
            }

            $xpath = new DOMXPath($document);
            $processingInstructions = $xpath->query('//processing-instruction()');
            if ($processingInstructions !== false && $processingInstructions->length > 0) {
                throw new InvalidArgumentException('Instruções de processamento não são permitidas em SVG.');
            }

            foreach ($xpath->query('//*') ?: [] as $element) {
                if (!$element instanceof DOMElement) {
                    continue;
                }
                $elementName = strtolower($element->localName);
                if (in_array($elementName, self::FORBIDDEN_ELEMENTS, true)) {
                    throw new InvalidArgumentException('O SVG contém elementos ativos ou externos não permitidos.');
                }
                if ($elementName === 'style') {
                    $styleContent = (string) $element->textContent;
                    if (preg_match('/(?:@import|expression\s*\(|-moz-binding|javascript\s*:|data\s*:)/i', $styleContent) === 1) {
                        throw new InvalidArgumentException('O SVG contém estilos externos ou executáveis não permitidos.');
                    }
                    self::validateUrlReferences($styleContent);
                }

                $attributes = [];
                foreach ($element->attributes as $attribute) {
                    $attributes[] = $attribute;
                }
                foreach ($attributes as $attribute) {
                    $attributeName = strtolower($attribute->localName);
                    $qualifiedName = strtolower($attribute->nodeName);
                    $value = trim($attribute->nodeValue ?? '');

                    if (str_starts_with($attributeName, 'on')) {
                        throw new InvalidArgumentException('Eventos JavaScript não são permitidos em SVG.');
                    }
                    if (in_array($attributeName, ['href', 'src'], true) || $qualifiedName === 'xlink:href') {
                        if ($value !== '' && !str_starts_with($value, '#')) {
                            throw new InvalidArgumentException('Referências externas não são permitidas em SVG.');
                        }
                    }
                    if (preg_match('/(?:javascript|vbscript|data)\s*:/i', $value) === 1
                        || preg_match('/(?:@import|expression\s*\(|-moz-binding)/i', $value) === 1) {
                        throw new InvalidArgumentException('O SVG contém conteúdo executável não permitido.');
                    }
                    self::validateUrlReferences($value);
                }
            }

            $sanitized = $document->saveXML($root);
            if (!is_string($sanitized) || $sanitized === '') {
                throw new InvalidArgumentException('Não foi possível normalizar o arquivo SVG.');
            }
            return $sanitized;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private static function validateUrlReferences(string $value): void
    {
        if (preg_match_all('/url\s*\(([^)]*)\)/i', $value, $matches) === false) {
            throw new InvalidArgumentException('O SVG contém uma referência inválida.');
        }
        foreach ($matches[1] ?? [] as $reference) {
            $reference = trim((string) $reference, " \t\n\r\0\x0B\"'");
            if ($reference !== '' && !str_starts_with($reference, '#')) {
                throw new InvalidArgumentException('URLs externas não são permitidas em SVG.');
            }
        }
    }
}
