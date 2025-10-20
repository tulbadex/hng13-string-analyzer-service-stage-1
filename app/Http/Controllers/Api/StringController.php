<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyzedString;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StringController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'value' => 'required|string'
        ]);
        
        $value = $request->input('value');
        $hash = hash('sha256', $value);
        
        if (AnalyzedString::find($hash)) {
            return response()->json(['error' => 'String already exists'], 409);
        }
        
        $analysis = AnalyzedString::analyzeString($value);
        $analyzedString = AnalyzedString::create($analysis);
        
        return response()->json([
            'id' => $analyzedString->id,
            'value' => $analyzedString->value,
            'properties' => [
                'length' => $analyzedString->length,
                'is_palindrome' => $analyzedString->is_palindrome,
                'unique_characters' => $analyzedString->unique_characters,
                'word_count' => $analyzedString->word_count,
                'sha256_hash' => $analyzedString->sha256_hash,
                'character_frequency_map' => $analyzedString->character_frequency_map
            ],
            'created_at' => $analyzedString->created_at->toISOString()
        ], 201);
    }
    
    public function show(string $value)
    {
        $hash = hash('sha256', $value);
        $analyzedString = AnalyzedString::find($hash);
        
        if (!$analyzedString) {
            return response()->json(['error' => 'String not found'], 404);
        }
        
        return response()->json([
            'id' => $analyzedString->id,
            'value' => $analyzedString->value,
            'properties' => [
                'length' => $analyzedString->length,
                'is_palindrome' => $analyzedString->is_palindrome,
                'unique_characters' => $analyzedString->unique_characters,
                'word_count' => $analyzedString->word_count,
                'sha256_hash' => $analyzedString->sha256_hash,
                'character_frequency_map' => $analyzedString->character_frequency_map
            ],
            'created_at' => $analyzedString->created_at->toISOString()
        ]);
    }
    
    public function index(Request $request)
    {
        $query = AnalyzedString::query();
        $filters = [];
        
        if ($request->has('is_palindrome')) {
            $isPalindrome = $request->is_palindrome === 'true' ? 1 : 0;
            $query->where('is_palindrome', $isPalindrome);
            $filters['is_palindrome'] = (bool)$isPalindrome;
        }
        
        if ($request->has('min_length')) {
            $minLength = (int) $request->min_length;
            $query->where('length', '>=', $minLength);
            $filters['min_length'] = $minLength;
        }
        
        if ($request->has('max_length')) {
            $maxLength = (int) $request->max_length;
            $query->where('length', '<=', $maxLength);
            $filters['max_length'] = $maxLength;
        }
        
        if ($request->has('word_count')) {
            $wordCount = (int) $request->word_count;
            $query->where('word_count', $wordCount);
            $filters['word_count'] = $wordCount;
        }
        
        if ($request->has('contains_character')) {
            $char = $request->contains_character;
            $query->whereRaw('character_frequency_map LIKE ?', ['%"' . $char . '":%']);
            $filters['contains_character'] = $char;
        }
        
        $strings = $query->get();
        
        return response()->json([
            'data' => $strings->map(function ($string) {
                return [
                    'id' => $string->id,
                    'value' => $string->value,
                    'properties' => [
                        'length' => $string->length,
                        'is_palindrome' => $string->is_palindrome,
                        'unique_characters' => $string->unique_characters,
                        'word_count' => $string->word_count,
                        'sha256_hash' => $string->sha256_hash,
                        'character_frequency_map' => $string->character_frequency_map
                    ],
                    'created_at' => $string->created_at->toISOString()
                ];
            }),
            'count' => $strings->count(),
            'filters_applied' => $filters
        ]);
    }
    
    public function filterByNaturalLanguage(Request $request)
    {
        $query = urldecode($request->input('query', ''));
        $filters = $this->parseNaturalLanguageQuery($query);
        
        if (empty($filters)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }
        
        $queryBuilder = AnalyzedString::query();
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'is_palindrome':
                    $queryBuilder->where('is_palindrome', $value);
                    break;
                case 'word_count':
                    $queryBuilder->where('word_count', $value);
                    break;
                case 'min_length':
                    $queryBuilder->where('length', '>=', $value);
                    break;
                case 'contains_character':
                    $queryBuilder->whereRaw('character_frequency_map LIKE ?', ['%"' . $value . '":%']);
                    break;
            }
        }
        
        $strings = $queryBuilder->get();
        
        return response()->json([
            'data' => $strings->map(function ($string) {
                return [
                    'id' => $string->id,
                    'value' => $string->value,
                    'properties' => [
                        'length' => $string->length,
                        'is_palindrome' => $string->is_palindrome,
                        'unique_characters' => $string->unique_characters,
                        'word_count' => $string->word_count,
                        'sha256_hash' => $string->sha256_hash,
                        'character_frequency_map' => $string->character_frequency_map
                    ],
                    'created_at' => $string->created_at->toISOString()
                ];
            }),
            'count' => $strings->count(),
            'interpreted_query' => [
                'original' => $query,
                'parsed_filters' => $filters
            ]
        ]);
    }
    
    public function destroy(string $value)
    {
        $hash = hash('sha256', $value);
        $analyzedString = AnalyzedString::find($hash);
        
        if (!$analyzedString) {
            return response()->json(['error' => 'String not found'], 404);
        }
        
        $analyzedString->delete();
        
        return response()->json(null, 204);
    }
    
    private function parseNaturalLanguageQuery(string $query): array
    {
        $filters = [];
        $query = strtolower(urldecode($query));
        
        // Palindrome detection
        if (preg_match('/palindrom/i', $query)) {
            $filters['is_palindrome'] = true;
        }
        
        // Word count detection
        if (preg_match('/single word|one word/', $query)) {
            $filters['word_count'] = 1;
        }
        if (preg_match('/(\d+)\s+words?/', $query, $matches)) {
            $filters['word_count'] = (int)$matches[1];
        }
        
        // Length detection
        if (preg_match('/longer than (\d+)/', $query, $matches)) {
            $filters['min_length'] = (int)$matches[1] + 1;
        }
        if (preg_match('/shorter than (\d+)/', $query, $matches)) {
            $filters['max_length'] = (int)$matches[1] - 1;
        }
        
        // Character detection
        if (preg_match('/contain.*letter ([a-z])/i', $query, $matches)) {
            $filters['contains_character'] = strtolower($matches[1]);
        }
        if (preg_match('/first vowel/', $query)) {
            $filters['contains_character'] = 'a';
        }
        
        return $filters;
    }
}
