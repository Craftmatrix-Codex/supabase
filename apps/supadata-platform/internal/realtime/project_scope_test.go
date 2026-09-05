package realtime

import (
	"context"
	"testing"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestProjectTopicKeySeparatesProjectsWithSameTopic(t *testing.T) {
	alpha := project.WithScope(context.Background(), project.Project{ID: "alpha"})
	beta := project.WithScope(context.Background(), project.Project{ID: "beta"})

	alphaKey := ProjectTopicKey(alpha, "realtime:public:events")
	betaKey := ProjectTopicKey(beta, "realtime:public:events")
	if alphaKey == betaKey {
		t.Fatalf("project topic keys collide: %q", alphaKey)
	}
	if ProjectTopicKey(context.Background(), "realtime:public:events") != "realtime:public:events" {
		t.Fatal("unscoped topic key changed")
	}
}
