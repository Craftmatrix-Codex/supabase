package registry

import (
	"context"
	"encoding/json"
	"os"
	"path/filepath"
	"testing"
)

func TestRegistrySelectsAndPersistsAnotherProject(t *testing.T) {
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

	selected, err := store.SelectProject(context.Background(), "second")
	if err != nil {
		t.Fatalf("SelectProject() error = %v", err)
	}
	if selected.ID != "second" || !selected.Current {
		t.Fatalf("unexpected selected project: %+v", selected)
	}
	current, err := store.CurrentProject(context.Background())
	if err != nil {
		t.Fatalf("CurrentProject() error = %v", err)
	}
	if current == nil || current.ID != "second" {
		t.Fatalf("selection was not persisted: %+v", current)
	}
}

func TestRegistrySelectionPreservesStorageBucketMetadata(t *testing.T) {
	dataDir := t.TempDir()
	fixture := map[string]any{
		"currentProjectId": "alpha",
		"projects": []any{
			map[string]any{
				"id": "alpha", "name": "Alpha", "status": "ready", "current": true,
				"scope": map[string]any{"storage": map[string]any{
					"bucket":  "supadata-alpha",
					"buckets": []any{map[string]any{"id": "alpha-only", "name": "alpha-only"}},
				}},
			},
			map[string]any{
				"id": "beta", "name": "Beta", "status": "ready", "current": false,
				"scope": map[string]any{"storage": map[string]any{
					"bucket":  "supadata-beta",
					"buckets": []any{map[string]any{"id": "beta-only", "name": "beta-only"}},
				}},
			},
		},
	}
	contents, err := json.Marshal(fixture)
	if err != nil {
		t.Fatalf("marshal fixture: %v", err)
	}
	if err := os.WriteFile(filepath.Join(dataDir, "registry.json"), contents, 0o640); err != nil {
		t.Fatalf("write fixture: %v", err)
	}

	store, err := New(Options{DataDir: dataDir})
	if err != nil {
		t.Fatalf("New() error = %v", err)
	}
	if _, err := store.SelectProject(context.Background(), "beta"); err != nil {
		t.Fatalf("SelectProject() error = %v", err)
	}

	var persisted map[string]any
	contents, err = os.ReadFile(filepath.Join(dataDir, "registry.json"))
	if err != nil {
		t.Fatalf("read persisted registry: %v", err)
	}
	if err := json.Unmarshal(contents, &persisted); err != nil {
		t.Fatalf("parse persisted registry: %v", err)
	}
	projects, ok := persisted["projects"].([]any)
	if !ok || len(projects) != 2 {
		t.Fatalf("unexpected persisted projects: %#v", persisted["projects"])
	}
	for _, raw := range projects {
		projectRecord := raw.(map[string]any)
		if projectRecord["id"] != "beta" {
			continue
		}
		scope, ok := projectRecord["scope"].(map[string]any)
		if !ok {
			t.Fatalf("scope was not persisted: %#v", projectRecord["scope"])
		}
		storage, ok := scope["storage"].(map[string]any)
		if !ok {
			t.Fatalf("storage scope was not persisted: %#v", scope["storage"])
		}
		buckets, ok := storage["buckets"].([]any)
		if !ok {
			t.Fatalf("storage metadata was not preserved: %#v", storage["buckets"])
		}
		if len(buckets) != 1 || buckets[0].(map[string]any)["id"] != "beta-only" {
			t.Fatalf("storage metadata was not preserved: %#v", storage["buckets"])
		}
		return
	}
	t.Fatal("beta project was not persisted")
}
