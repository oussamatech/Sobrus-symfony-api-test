<?php

namespace App\Service;

class ContentValidator
{
    /**
     * Validate if content contains banned words.
     *
     * @param string $content
     * @param array $banned
     * @return array
     */
    public function validateContent(string $content, array $banned): array
    {
        $content = strtolower($content);
        $words = preg_split('/\s+/', $content);
        $bannedSet = array_flip($banned);

        $invalidWords = array_filter($words, function($word) use ($bannedSet) {
            return isset($bannedSet[$word]);
        });

        return array_unique($invalidWords);
    }
}