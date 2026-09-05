package project

import "testing"

func TestBuildScopeCreatesDistinctSharedInfrastructureNamespaces(t *testing.T) {
	alpha, err := BuildScope("alpha-project", "supabase.craftmatrix.org")
	if err != nil {
		t.Fatalf("BuildScope(alpha) error = %v", err)
	}
	beta, err := BuildScope("beta-project", "supabase.craftmatrix.org")
	if err != nil {
		t.Fatalf("BuildScope(beta) error = %v", err)
	}

	if alpha.Database.Name != "supadata_alpha_project" {
		t.Fatalf("alpha database = %q", alpha.Database.Name)
	}
	if alpha.Database.Role != "supadata_alpha_project_runtime" {
		t.Fatalf("alpha role = %q", alpha.Database.Role)
	}
	if alpha.Storage.Bucket != "supadata-alpha-project" {
		t.Fatalf("alpha bucket = %q", alpha.Storage.Bucket)
	}
	if alpha.PublicURL != "https://alpha-project.supabase.craftmatrix.org" {
		t.Fatalf("alpha public URL = %q", alpha.PublicURL)
	}
	if alpha.Database.Name == beta.Database.Name || alpha.Database.Role == beta.Database.Role || alpha.Storage.Bucket == beta.Storage.Bucket {
		t.Fatalf("project scopes collide: alpha=%+v beta=%+v", alpha, beta)
	}
}

func TestBuildScopeRejectsInvalidProjectID(t *testing.T) {
	if _, err := BuildScope("../escape", "supabase.example.com"); err == nil {
		t.Fatal("invalid project ID was accepted")
	}
}

func TestBuildScopeAllowsNoPublicHost(t *testing.T) {
	scope, err := BuildScope("local", "")
	if err != nil {
		t.Fatalf("BuildScope() error = %v", err)
	}
	if scope.PublicURL != "" {
		t.Fatalf("public URL = %q, want empty", scope.PublicURL)
	}
}
