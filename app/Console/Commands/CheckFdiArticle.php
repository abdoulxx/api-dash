<?php

namespace App\Console\Commands;

use App\Models\FdiArticle;
use Illuminate\Console\Command;

class CheckFdiArticle extends Command
{
    protected $signature = 'check:fdi-article {ulid}';
    protected $description = 'Vérifier si un article FDI existe avec un ULID';

    public function handle()
    {
        $ulid = $this->argument('ulid');
        
        $this->info("Recherche de l'article avec ULID: {$ulid}");
        
        // Recherche directe par ULID
        $article = FdiArticle::where('ulid', $ulid)->first();
        
        if ($article) {
            $this->info("✅ Article trouvé !");
            $this->line("ID: {$article->id}");
            $this->line("ULID: {$article->ulid}");
            $this->line("numero_fdi: {$article->numero_fdi}");
            $this->line("numart: {$article->numart}");
            $this->line("description_marchandise: {$article->description_marchandise}");
            
            $this->info("\nToutes les données:");
            print_r($article->toArray());
        } else {
            $this->error("❌ Article non trouvé avec cet ULID");
            
            $this->info("\nArticles existants dans la base:");
            $articles = FdiArticle::select('id', 'ulid', 'numero_fdi', 'numart')
                ->limit(10)
                ->get();
            
            if ($articles->isEmpty()) {
                $this->warn("Aucun article trouvé dans la base de données");
            } else {
                $this->table(
                    ['ID', 'ULID', 'numero_fdi', 'numart'],
                    $articles->map(function ($a) {
                        return [
                            $a->id,
                            $a->ulid ?? 'NULL',
                            $a->numero_fdi ?? 'NULL',
                            $a->numart ?? 'NULL',
                        ];
                    })->toArray()
                );
            }
        }
        
        // Test du route model binding
        $this->info("\nTest du route model binding:");
        try {
            $model = new FdiArticle();
            $resolved = $model->resolveRouteBinding($ulid);
            
            if ($resolved) {
                $this->info("✅ Route model binding fonctionne !");
                $this->line("ID: {$resolved->id}");
            } else {
                $this->error("❌ Route model binding n'a pas trouvé l'article");
            }
        } catch (\Exception $e) {
            $this->error("❌ Route model binding erreur: " . $e->getMessage());
        }
        
        return Command::SUCCESS;
    }
}



