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
        // Check if request has value key
        if (!$request->has('value')) {
            // Check if raw input contains 'value' but failed to parse (malformed JSON with numbers)
            $rawInput = $request->getContent();
            if (strpos($rawInput, '"value"') !== false || strpos($rawInput, 'value') !== false) {
                return response()->json(['error' => 'Invalid data type for value field'], 422);
            }
            return response()->json(['error' => 'Invalid request body or missing value field'], 400);
        }
        
        $value = $request->input('value');
        
        // Check if value is not a string (numeric, boolean, null, array, object)
        if (!is_string($value)) {
            return response()->json(['error' => 'Invalid data type for value field'], 422);
        }
        
        // Check if string is empty
        if (empty($value)) {
            return response()->json(['error' => 'Invalid data type for value field'], 422);
        }
        
        // Check if value is a numeric string (like "123", "000", "0000")
        if (is_numeric($value)) {
            return response()->json(['error' => 'Invalid data type for value field'], 422);
        }
        
        $validation = $request->validate([
            'value' => 'required|string|min:1',
        ]);

        $value = $validation['value'];
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
        
        // Validate is_palindrome parameter
        if ($request->has('is_palindrome')) {
            $value = $request->get('is_palindrome');
            if (!in_array($value, ['true', 'false'], true)) {
                return response()->json(['error' => 'Invalid query parameter values or types'], 400);
            }
            $isPalindrome = $value === 'true';
            $query->where('is_palindrome', $isPalindrome ? 1 : 0);
            $filters['is_palindrome'] = $isPalindrome;
        }
        
        // Validate min_length parameter
        if ($request->has('min_length')) {
            $value = $request->get('min_length');
            if (!is_numeric($value) || (int)$value < 0) {
                return response()->json(['error' => 'Invalid query parameter values or types'], 400);
            }
            $minLength = (int)$value;
            $query->where('length', '>=', $minLength);
            $filters['min_length'] = $minLength;
        }
        
        // Validate max_length parameter
        if ($request->has('max_length')) {
            $value = $request->get('max_length');
            if (!is_numeric($value) || (int)$value < 0) {
                return response()->json(['error' => 'Invalid query parameter values or types'], 400);
            }
            $maxLength = (int)$value;
            $query->where('length', '<=', $maxLength);
            $filters['max_length'] = $maxLength;
        }
        
        // Validate word_count parameter
        if ($request->has('word_count')) {
            $value = $request->get('word_count');
            if (!is_numeric($value) || (int)$value < 0) {
                return response()->json(['error' => 'Invalid query parameter values or types'], 400);
            }
            $wordCount = (int)$value;
            $query->where('word_count', $wordCount);
            $filters['word_count'] = $wordCount;
        }
        
        // Validate contains_character parameter
        if ($request->has('contains_character')) {
            $value = $request->get('contains_character');
            if (!is_string($value) || strlen($value) !== 1) {
                return response()->json(['error' => 'Invalid query parameter values or types'], 400);
            }
            $query->where('value', 'LIKE', '%' . $value . '%');
            $filters['contains_character'] = $value;
        }
        
        $strings = $query->get();
        
        return response()->json([
            'data' => $strings->map(function ($string) {
                return [
                    'id' => $string->id,
                    'value' => $string->value,
                    'properties' => [
                        'length' => $string->length,
                        'is_palindrome' => (bool)$string->is_palindrome,
                        'unique_characters' => $string->unique_characters,
                        'word_count' => $string->word_count,
                        'sha256_hash' => $string->sha256_hash,
                        'character_frequency_map' => $string->character_frequency_map
                    ],
                    'created_at' => $string->created_at->toISOString()
                ];
            })->values(),
            'count' => $strings->count(),
            'filters_applied' => $filters
        ]);
    }
    
    public function filterByNaturalLanguage(Request $request)
    {
        $query = $request->get('query', '');
        
        if (empty($query)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }
        
        $filters = $this->parseNaturalLanguageQuery($query);
        
        if (empty($filters)) {
            return response()->json(['error' => 'Unable to parse natural language query'], 400);
        }
        
        $queryBuilder = AnalyzedString::query();
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'is_palindrome':
                    $queryBuilder->where('is_palindrome', $value ? 1 : 0);
                    break;
                case 'word_count':
                    $queryBuilder->where('word_count', $value);
                    break;
                case 'min_length':
                    $queryBuilder->where('length', '>=', $value);
                    break;
                case 'max_length':
                    $queryBuilder->where('length', '<=', $value);
                    break;
                case 'contains_character':
                    $queryBuilder->where('value', 'LIKE', '%' . $value . '%');
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
                        'is_palindrome' => (bool)$string->is_palindrome,
                        'unique_characters' => $string->unique_characters,
                        'word_count' => $string->word_count,
                        'sha256_hash' => $string->sha256_hash,
                        'character_frequency_map' => $string->character_frequency_map
                    ],
                    'created_at' => $string->created_at->toISOString()
                ];
            })->values(),
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
        $query = strtolower(trim($query));
        
        // Palindrome detection (matches Python version)
        if (strpos($query, 'palindrome') !== false || strpos($query, 'palindromic') !== false) {
            $filters['is_palindrome'] = true;
        }
        
        // Length detection (matches Python version)
        if (preg_match('/longer than (\d+)/', $query, $matches)) {
            $filters['min_length'] = (int)$matches[1] + 1;
        }
        if (preg_match('/shorter than (\d+)/', $query, $matches)) {
            $filters['max_length'] = (int)$matches[1] - 1;
        }
        
        // Word count detection (matches Python version)
        if (strpos($query, 'single word') !== false) {
            $filters['word_count'] = 1;
        } elseif (strpos($query, 'two words') !== false) {
            $filters['word_count'] = 2;
        } elseif (strpos($query, 'three words') !== false) {
            $filters['word_count'] = 3;
        }
        
        // Character detection (matches Python version)
        if (preg_match('/contain(?:s|ing)? the letter ([a-z])/', $query, $matches)) {
            $filters['contains_character'] = $matches[1];
        }
        
        // Handle special phrase "first vowel" (matches Python version)
        if (strpos($query, 'first vowel') !== false) {
            $filters['contains_character'] = 'a';
        }
        
        return $filters;
    }
}
