<?php

namespace Tests\Feature;

use App\Models\AnalyzedString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StringAnalyzerTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_api_health_check()
    {
        $response = $this->getJson('/');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'version',
                     'endpoints'
                 ]);
    }
    
    public function test_can_create_analyzed_string()
    {
        $response = $this->postJson('/strings', [
            'value' => 'hello world'
        ]);
        
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id',
                     'value',
                     'properties' => [
                         'length',
                         'is_palindrome',
                         'unique_characters',
                         'word_count',
                         'sha256_hash',
                         'character_frequency_map'
                     ],
                     'created_at'
                 ]);
    }
    
    public function test_cannot_create_duplicate_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->postJson('/strings', ['value' => 'test']);
        
        $response->assertStatus(409);
    }
    
    public function test_can_retrieve_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->getJson('/strings/test');
        
        $response->assertStatus(200)
                 ->assertJson(['value' => 'test']);
    }
    
    public function test_can_filter_strings()
    {
        $this->postJson('/strings', ['value' => 'racecar']);
        $this->postJson('/strings', ['value' => 'hello']);
        
        $response = $this->getJson('/strings?is_palindrome=true');
        
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }
    
    public function test_can_delete_string()
    {
        $this->postJson('/strings', ['value' => 'test']);
        
        $response = $this->deleteJson('/strings/test');
        
        $response->assertStatus(204);
    }
    
    public function test_post_missing_value_field_returns_400()
    {
        $response = $this->postJson('/strings', []);
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Missing value field']);
    }
    
    public function test_post_invalid_data_type_returns_422()
    {
        $response = $this->postJson('/strings', ['value' => 123]);
        
        $response->assertStatus(422)
                 ->assertJson(['error' => 'Value must be a string']);
    }
    
    public function test_post_null_value_returns_422()
    {
        $response = $this->postJson('/strings', ['value' => null]);
        
        $response->assertStatus(422)
                 ->assertJson(['error' => 'Value must be a string']);
    }
    
    public function test_post_array_value_returns_422()
    {
        $response = $this->postJson('/strings', ['value' => ['test']]);
        
        $response->assertStatus(422)
                 ->assertJson(['error' => 'Value must be a string']);
    }
    
    public function test_get_strings_with_invalid_parameters_returns_400()
    {
        $response = $this->getJson('/strings?is_palindrome=invalid');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid query parameter values or types']);
    }
    
    public function test_get_strings_with_invalid_min_length_returns_400()
    {
        $response = $this->getJson('/strings?min_length=abc');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid query parameter values or types']);
    }
    
    public function test_get_strings_with_invalid_contains_character_returns_400()
    {
        $response = $this->getJson('/strings?contains_character=abc');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Invalid query parameter values or types']);
    }
    
    public function test_natural_language_filter_single_word_palindromic()
    {
        $this->postJson('/strings', ['value' => 'racecar']);
        $this->postJson('/strings', ['value' => 'hello world']);
        
        $response = $this->getJson('/strings/filter-by-natural-language?query=all%20single%20word%20palindromic%20strings');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data',
                     'count',
                     'interpreted_query' => [
                         'original',
                         'parsed_filters'
                     ]
                 ])
                 ->assertJsonPath('interpreted_query.parsed_filters.word_count', 1)
                 ->assertJsonPath('interpreted_query.parsed_filters.is_palindrome', true);
    }
    
    public function test_natural_language_filter_longer_than_characters()
    {
        $this->postJson('/strings', ['value' => 'short']);
        $this->postJson('/strings', ['value' => 'this is a very long string']);
        
        $response = $this->getJson('/strings/filter-by-natural-language?query=strings%20longer%20than%2010%20characters');
        
        $response->assertStatus(200)
                 ->assertJsonPath('interpreted_query.parsed_filters.min_length', 11);
    }
    
    public function test_natural_language_filter_containing_letter()
    {
        $this->postJson('/strings', ['value' => 'zebra']);
        $this->postJson('/strings', ['value' => 'hello']);
        
        $response = $this->getJson('/strings/filter-by-natural-language?query=strings%20containing%20the%20letter%20z');
        
        $response->assertStatus(200)
                 ->assertJsonPath('interpreted_query.parsed_filters.contains_character', 'z');
    }
    
    public function test_natural_language_filter_empty_query_returns_400()
    {
        $response = $this->getJson('/strings/filter-by-natural-language?query=');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Unable to parse natural language query']);
    }
    
    public function test_natural_language_filter_unparseable_query_returns_400()
    {
        $response = $this->getJson('/strings/filter-by-natural-language?query=random%20gibberish%20text');
        
        $response->assertStatus(400)
                 ->assertJson(['error' => 'Unable to parse natural language query']);
    }
    
    public function test_get_strings_filtering_works_correctly()
    {
        // Create test data
        $this->postJson('/strings', ['value' => 'racecar']); // palindrome, 1 word, 7 chars
        $this->postJson('/strings', ['value' => 'hello world']); // not palindrome, 2 words, 11 chars
        $this->postJson('/strings', ['value' => 'a']); // palindrome, 1 word, 1 char
        
        // Test palindrome filter
        $response = $this->getJson('/strings?is_palindrome=true');
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('filters_applied.is_palindrome', true);
        
        // Test word count filter
        $response = $this->getJson('/strings?word_count=1');
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('filters_applied.word_count', 1);
        
        // Test min length filter
        $response = $this->getJson('/strings?min_length=5');
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('filters_applied.min_length', 5);
        
        // Test max length filter
        $response = $this->getJson('/strings?max_length=5');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('filters_applied.max_length', 5);
        
        // Test contains character filter
        $response = $this->getJson('/strings?contains_character=a');
        $response->assertStatus(200)
                 ->assertJsonPath('filters_applied.contains_character', 'a');
    }
}
