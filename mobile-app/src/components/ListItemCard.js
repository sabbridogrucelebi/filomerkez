import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { colors, spacing, radius, typography, shadow } from '../theme';
import Badge from './Badge';

export default function ListItemCard({ avatarText, avatarIcon, accent = 'info', title, subtitle, badges = [], right, onPress, onLongPress }) {
    const accentColor = colors[accent] || { 100: '#E2E8F0', 700: colors.textPrimary };

    return (
        <TouchableOpacity 
            style={styles.card} 
            activeOpacity={0.7} 
            onPress={onPress} 
            onLongPress={onLongPress}
            disabled={!onPress && !onLongPress}
        >
            <View style={[styles.avatar, { backgroundColor: accentColor[100] }]}>
                {avatarIcon ? (
                    <Icon name={avatarIcon} size={24} color={accentColor[700]} />
                ) : (
                    <Text style={[styles.avatarText, { color: accentColor[700] }]}>{avatarText}</Text>
                )}
            </View>
            <View style={styles.content}>
                <Text style={styles.title} numberOfLines={1}>{title}</Text>
                {subtitle && <Text style={styles.subtitle} numberOfLines={1}>{subtitle}</Text>}
                {badges.length > 0 && (
                    <View style={styles.badges}>
                        {badges.map((b, i) => (
                            <Badge key={i} label={b.label} tone={b.tone || 'neutral'} />
                        ))}
                    </View>
                )}
            </View>
            <View style={styles.right}>
                {right ? right : (onPress ? <Icon name="chevron-right" size={24} color={colors.textMuted} /> : null)}
            </View>
        </TouchableOpacity>
    );
}

const styles = StyleSheet.create({
    card: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: colors.surface,
        borderRadius: radius.lg,
        padding: spacing.lg,
        marginBottom: spacing.md,
        ...shadow.sm,
    },
    avatar: {
        width: 48,
        height: 48,
        borderRadius: radius.full,
        alignItems: 'center',
        justifyContent: 'center',
        marginRight: spacing.lg,
    },
    avatarText: {
        ...typography.title,
    },
    content: {
        flex: 1,
    },
    title: {
        ...typography.heading,
        color: colors.textPrimary,
        marginBottom: 2,
    },
    subtitle: {
        ...typography.body,
        color: colors.textSecondary,
    },
    badges: {
        flexDirection: 'row',
        flexWrap: 'wrap',
        marginTop: spacing.sm,
        gap: spacing.sm,
    },
    right: {
        marginLeft: spacing.md,
        justifyContent: 'center',
    }
});
