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
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:51200', // 50MB
            'is_published' => 'boolean'
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_img_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $imageUrl = asset('storage/blog/' . $filename);
        }

        $videoUrl = null;
        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = time() . '_vid_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog/videos'), $filename);
            $videoUrl = asset('storage/blog/videos/' . $filename);
        }

        $post = Post::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . uniqid(),
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'image' => $imageUrl,
            'video' => $videoUrl,
            'is_published' => $request->is_published ?? false,
            'published_at' => ($request->is_published ?? false) ? now() : null,
            'author_id' => $request->user()->id
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
        
        $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'excerpt' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'video' => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-msvideo|max:51200',
            'is_published' => 'boolean'
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_img_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $post->image = asset('storage/blog/' . $filename);
        }

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = time() . '_vid_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog/videos'), $filename);
            $post->video = asset('storage/blog/videos/' . $filename);
        }

        if ($request->has('title')) {
            $post->title = $request->title;
        }

        if ($request->has('content')) $post->content = $request->content;
        if ($request->has('excerpt')) $post->excerpt = $request->excerpt;
        
        if ($request->has('is_published')) {
            if (!$post->is_published && $request->is_published) {
                $post->published_at = now();
            }
            $post->is_published = $request->is_published;
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
            'user_id' => $request->user()->id,
            'content' => $request->content
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

        $existingLike = $post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
        } else {
            $post->likes()->create([
                'user_id' => $user->id
            ]);
            $liked = true;
        }

        return response()->json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $post->likes()->count()
        ]);
    }
}
