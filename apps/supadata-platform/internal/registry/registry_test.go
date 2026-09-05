package registry

import (
	"context"
	"os"
	"path/filepath"
	"testing"
)

func TestRegistryPersistsProjectsAndCurrentSelection(t *testing.T) {
	dataDir := t.TempDir()
	store, err := New(Options{DataDir: dataDir})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}

	first, err := store.CreateProject(context.Background(), "First Project", "")
	if err != nil {
		t.Fatalf("CreateProject(first) error = %v", err)
	}
	if first.ID != "first-project" || first.Status != "registered" || !first.Current {
		t.Fatalf("unexpected first project: %+v", first)
	}

	second, err := store.CreateProject(context.Background(), "Second Project", "second")
	if err != nil {
		t.Fatalf("CreateProject(second) error = %v", err)
	}
	if second.Current {
		t.Fatalf("second project unexpectedly current: %+v", second)
	}

	projects, err := store.ListProjects(context.Background())
	if err != nil {
		t.Fatalf("ListProjects() error = %v", err)
	}
	if len(projects) != 2 || !projects[0].Current || projects[1].Current {
		t.Fatalf("unexpected project list: %+v", projects)
	}

	reloaded, err := New(Options{DataDir: dataDir})
	if err != nil {
		t.Fatalf("reloading registry: %v", err)
	}
	current, err := reloaded.CurrentProject(context.Background())
	if err != nil {
		t.Fatalf("CurrentProject() error = %v", err)
	}
	if current == nil || current.ID != "first-project" {
		t.Fatalf("unexpected current project after reload: %+v", current)
	}
	if _, err := os.Stat(filepath.Join(dataDir, "registry.json")); err != nil {
		t.Fatalf("registry file was not persisted: %v", err)
	}
}

func TestRegistryResolveProjectDoesNotChangeCurrentSelection(t *testing.T) {
	store, err := New(Options{DataDir: t.TempDir()})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "First", "first"); err != nil {
		t.Fatalf("CreateProject(first) error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "Second", "second"); err != nil {
		t.Fatalf("CreateProject(second) error = %v", err)
	}

	resolved, err := store.ResolveProject(context.Background(), "second")
	if err != nil {
		t.Fatalf("ResolveProject() error = %v", err)
	}
	if resolved.ID != "second" {
		t.Fatalf("resolved project = %+v, want second", resolved)
	}
	current, err := store.CurrentProject(context.Background())
	if err != nil {
		t.Fatalf("CurrentProject() error = %v", err)
	}
	if current == nil || current.ID != "first" {
		t.Fatalf("current project = %+v, want first", current)
	}
}

func TestRegistryPersistsProjectResourceScopes(t *testing.T) {
	store, err := New(Options{DataDir: t.TempDir(), PublicHost: "supabase.example.com"})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	alpha, err := store.CreateProject(context.Background(), "Alpha", "alpha")
	if err != nil {
		t.Fatalf("CreateProject(alpha) error = %v", err)
	}
	beta, err := store.CreateProject(context.Background(), "Beta", "beta")
	if err != nil {
		t.Fatalf("CreateProject(beta) error = %v", err)
	}
	if alpha.Scope.Database.Name == beta.Scope.Database.Name || alpha.Scope.Storage.Bucket == beta.Scope.Storage.Bucket {
		t.Fatalf("project scopes collide: alpha=%+v beta=%+v", alpha.Scope, beta.Scope)
	}
	if alpha.Scope.PublicURL != "https://alpha.supabase.example.com" {
		t.Fatalf("alpha public URL = %q", alpha.Scope.PublicURL)
	}
}

func TestRegistryRejectsDuplicateAndEmptyProjects(t *testing.T) {
	store, err := New(Options{DataDir: t.TempDir()})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "Demo", "demo"); err != nil {
		t.Fatalf("initial CreateProject() error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "Other", "demo"); err == nil {
		t.Fatal("duplicate project was accepted")
	}
	if _, err := store.CreateProject(context.Background(), "   ", ""); err == nil {
		t.Fatal("empty project was accepted")
	}
}

func TestRegistryWritesAtomicRegistryFile(t *testing.T) {
	store, err := New(Options{DataDir: t.TempDir()})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	if _, err := store.CreateProject(context.Background(), "Atomic", ""); err != nil {
		t.Fatalf("CreateProject() error = %v", err)
	}
	files, err := os.ReadDir(filepath.Dir(store.registryPath))
	if err != nil {
		t.Fatalf("ReadDir() error = %v", err)
	}
	for _, file := range files {
		if filepath.Ext(file.Name()) == ".tmp" {
			t.Fatalf("temporary registry file left behind: %s", file.Name())
		}
	}
}
