<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Post;


class BlogController extends Controller
{
    /**
     * List published posts
     */
    public function index(Request $request)
    {
        $limit = $request->get('limit', 10);
        
        $posts = Post::with('author')
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
        $post = Post::with('author')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
            
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
            'is_published' => 'boolean'
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $imageUrl = asset('storage/blog/' . $filename);
        }

        $post = Post::create([
            'title' => $request->title,
            'slug' => \Illuminate\Support\Str::slug($request->title) . '-' . uniqid(),
            'content' => $request->content,
            'excerpt' => $request->excerpt,
            'image' => $imageUrl,
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
            'is_published' => 'boolean'
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('storage/blog'), $filename);
            $post->image = asset('storage/blog/' . $filename);
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
}
