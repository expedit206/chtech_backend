<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\BlogComment;
use Illuminate\Support\Str;

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
            'title' => 'required_without:titre|string|max:255',
            'titre' => 'required_without:title|string|max:255',
            'content' => 'required_without:contenu|string',
            'contenu' => 'required_without:content|string',
            'excerpt' => 'nullable|string',
            'extrait' => 'nullable|string',
            'is_published' => 'sometimes'
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpg,jpeg,png,webp|max:5120';
        } else {
            $rules['image'] = 'nullable|string';
        }

        if ($request->hasFile('video')) {
            $rules['video'] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:51200';
        } else {
            $rules['video'] = 'nullable|string';
        }

        $request->validate($rules);

        $imageUrl = $request->has('image') && is_string($request->image) ? $request->image : null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_img_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $imageUrl = asset('storage/blog/' . $filename);
        }

        $videoUrl = $request->has('video') && is_string($request->video) ? $request->video : null;
        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = time() . '_vid_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog/videos'), $filename);
            $videoUrl = asset('storage/blog/videos/' . $filename);
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
            'titre' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'contenu' => 'nullable|string',
            'excerpt' => 'nullable|string',
            'extrait' => 'nullable|string',
            'is_published' => 'sometimes'
        ];

        if ($request->hasFile('image')) {
            $rules['image'] = 'image|mimes:jpg,jpeg,png,webp|max:5120';
        } else {
            $rules['image'] = 'nullable|string';
        }

        if ($request->hasFile('video')) {
            $rules['video'] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:51200';
        } else {
            $rules['video'] = 'nullable|string';
        }

        $request->validate($rules);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_img_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $post->image = asset('storage/blog/' . $filename);
        } else if ($request->has('image') && is_string($request->image)) {
            $post->image = $request->image;
        }

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = time() . '_vid_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog/videos'), $filename);
            $post->video = asset('storage/blog/videos/' . $filename);
        } else if ($request->has('video') && is_string($request->video)) {
            $post->video = $request->video;
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
