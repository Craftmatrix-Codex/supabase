package realtime

import (
	"context"
	"strings"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func ProjectTopicKey(ctx context.Context, topic string) string {
	scope, ok := project.ScopeFromContext(ctx)
	if !ok || strings.TrimSpace(scope.ID) == "" {
		return topic
	}
	return scope.ID + "\x00" + topic
}
