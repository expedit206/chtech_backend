<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\BlogComment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    /**
     * List published posts
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $posts = Post::with(['author'])
            ->withCount(['comments', 'likes'])
            ->where('is_published', true)
            ->latest('published_at')
            ->paginate($limit);
            
        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Show a single post by slug
     */
    public function show($slug)
    {
        $post = Post::with(['author'])
            ->withCount(['comments', 'likes'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
            
        // Check if user liked post
        if (auth('sanctum')->check()) {
            $post->is_liked = $post->isLikedBy(auth('sanctum')->user());
        }
            
        // Increment views
        $post->increment('views_count');
        
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * List all posts for admin (including unpublished)
     */
    public function adminIndex(Request $request)
    {
        $limit = $request->get('limit', 20);
        $posts = Post::with('author')
            ->withCount(['comments', 'likes'])
            ->latest()
            ->paginate($limit);
            
        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }

    /**
     * Store a new post
     */
    public function store(Request $request)
    {
        $rules = [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'is_published' => 'sometimes'
        ];

        // Advanced image validation: handle both file and URL string
        $imageRules = ['nullable'];
        if ($request->hasFile('image')) {
            $imageRules[] = 'image';
            $imageRules[] = 'mimes:jpg,jpeg,png,webp,gif';
            $imageRules[] = 'max:10240';
        } elseif ($request->has('image') && is_string($request->image) && !empty($request->image)) {
            $imageRules[] = 'url';
        }
        $rules['image'] = $imageRules;

        // Same for video
        $videoRules = ['nullable'];
        if ($request->hasFile('video')) {
            $videoRules[] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo';
            $videoRules[] = 'max:102400';
        } elseif ($request->has('video') && is_string($request->video) && !empty($request->video)) {
            $videoRules[] = 'url';
        }
        $rules['video'] = $videoRules;

        $request->validate($rules, [
            'image.image' => "Le fichier doit être une image.",
            'image.mimes' => "L'image doit être au format jpg, jpeg, png, webp ou gif.",
            'image.max' => "L'image ne doit pas dépasser 10 Mo.",
            'image.url' => "Le lien de l'image doit être une URL valide.",
            'video.url' => "Le lien de la vidéo doit être une URL valide.",
            'title.required' => "Le titre est obligatoire.",
            'content.required' => "Le contenu est obligatoire.",
        ]);

        $imageUrl = is_string($request->input('image')) ? $request->input('image') : null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('blog', 'public');
            $imageUrl = $path;
        }

        $videoUrl = is_string($request->input('video')) ? $request->input('video') : null;
        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('blog/videos', 'public');
            $videoUrl = $path;
        }

        $title = $request->input('title') ?? $request->input('titre');
        $content = $request->input('content') ?? $request->input('contenu');
        $excerpt = $request->input('excerpt') ?? $request->input('extrait');
        $isPublished = false;
        if ($request->has('is_published')) {
            $isPublished = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
        }

        $post = Post::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . uniqid(),
            'content' => $content,
            'excerpt' => $excerpt,
            'image' => $imageUrl,
            'video' => $videoUrl,
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
            'author_id' => $request->user()->getAuthIdentifier()
        ]);

        return response()->json([
            'success' => true,
            'data' => $post
        ], 201);
    }

    /**
     * Update an existing post
     */
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);
        
        $rules = [
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'is_published' => 'sometimes'
        ];

        $imageRules = ['nullable'];
        if ($request->hasFile('image')) {
            $imageRules[] = 'image';
            $imageRules[] = 'mimes:jpg,jpeg,png,webp,gif';
            $imageRules[] = 'max:10240';
        } elseif ($request->has('image') && is_string($request->image) && !empty($request->image)) {
            $imageRules[] = 'url';
        }
        $rules['image'] = $imageRules;

        $videoRules = ['nullable'];
        if ($request->hasFile('video')) {
            $videoRules[] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo';
            $videoRules[] = 'max:102400';
        } elseif ($request->has('video') && is_string($request->video) && !empty($request->video)) {
            $videoRules[] = 'url';
        }
        $rules['video'] = $videoRules;

        $request->validate($rules, [
            'image.image' => "Le fichier doit être une image.",
            'image.mimes' => "L'image doit être au format jpg, jpeg, png, webp ou gif.",
            'image.max' => "L'image ne doit pas dépasser 10 Mo.",
            'image.url' => "Le lien de l'image doit être une URL valide.",
            'video.url' => "Le lien de la vidéo doit être une URL valide.",
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('blog', 'public');
            $post->image = $path;
        } else if ($request->has('image') && is_string($request->input('image'))) {
            $post->image = $request->input('image');
        }

        if ($request->hasFile('video')) {
            $path = $request->file('video')->store('blog/videos', 'public');
            $post->video = $path;
        } else if ($request->has('video') && is_string($request->input('video'))) {
            $post->video = $request->input('video');
        }

        if ($request->has('title')) $post->title = $request->input('title');
        else if ($request->has('titre')) $post->title = $request->input('titre');

        if ($request->has('content')) $post->content = $request->input('content');
        else if ($request->has('contenu')) $post->content = $request->input('contenu');

        if ($request->has('excerpt')) $post->excerpt = $request->input('excerpt');
        else if ($request->has('extrait')) $post->excerpt = $request->input('extrait');
        
        if ($request->has('is_published')) {
            $isPublished = filter_var($request->is_published, FILTER_VALIDATE_BOOLEAN);
            if (!$post->is_published && $isPublished) {
                $post->published_at = now();
            }
            $post->is_published = $isPublished;
        }

        $post->save();

        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * Toggle publish status
     */
    public function togglePublish($id)
    {
        $post = Post::findOrFail($id);
        $post->is_published = !$post->is_published;
        if ($post->is_published && !$post->published_at) {
            $post->published_at = now();
        }
        $post->save();
        
        return response()->json([
            'success' => true,
            'data' => $post
        ]);
    }

    /**
     * Delete a post
     */
    public function destroy($id)
    {
        $post = Post::findOrFail($id);
        $post->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Article supprimé'
        ]);
    }

    /**
     * Get comments for a post
     */
    public function getComments($slug)
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        
        $comments = $post->comments()
            ->with('user')
            ->latest()
            ->paginate(20);
            
        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    /**
     * Store a comment
     */
    public function storeComment(Request $request, $slug)
    {

      
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $post = Post::where('slug', $slug)->firstOrFail();
        
    
        $comment = $post->comments()->create([
            'user_id' => $request->user()->getAuthIdentifier(),
            'content' => $request->input('content')
        ]);
    //         return response()->json([
    //     'success' => true,
    //     //'data' => $comment
    //     'data' => $request->input('content')
    // ], 201);
        $comment->load('user');

        return response()->json([
            'success' => true,
            'data' => $comment
        ], 201);
    }

    /**
     * Toggle like on a post
     */
    public function toggleLike(Request $request, $slug)
    {
        $post = Post::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $existingLike = $post->likes()->where('user_id', $user->getAuthIdentifier())->first();

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
        } else {
            $post->likes()->create([
                'user_id' => $user->getAuthIdentifier()
            ]);
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $post->likes()->count()
        ]);
    }

    /**
     * Search for posts by title or content
     */
    public function search(Request $request)
    {
        $query = $request->get('q', '');
        
        if (!$query || strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $posts = Post::with(['author'])
            ->withCount(['comments', 'likes'])
            ->where('is_published', true)
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest('published_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $posts
        ]);
    }
}
