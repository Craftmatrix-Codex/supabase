package registry

import (
	"context"
	"testing"
)

func TestSetPublicDatabaseMetadataBackfillsExistingProjects(t *testing.T) {
	store, err := New(Options{DataDir: t.TempDir()})
	if err != nil {
		t.Fatal(err)
	}
	if _, err := store.CreateProject(context.Background(), "Demo", "demo"); err != nil {
		t.Fatal(err)
	}
	if err := store.SetPublicDatabaseMetadata("13.140.162.195", 5432, "cmx", "postgres", "postgresql://postgres:***@13.140.162.195:5432/cmx"); err != nil {
		t.Fatal(err)
	}
	project, err := store.CurrentProject(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if project == nil || project.DatabaseHost != "13.140.162.195" || project.DatabasePort != 5432 || project.DatabaseName != "cmx" || project.ConnectionString != "postgresql://postgres:***@13.140.162.195:5432/cmx" {
		t.Fatalf("public database metadata not persisted: %+v", project)
	}
}
