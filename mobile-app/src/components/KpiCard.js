import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { colors, spacing, radius, typography, shadow } from '../theme';

export default function KpiCard({ label, value, delta, deltaDirection = 'neutral', icon, accent = 'info' }) {
    const accentColor = colors[accent] || { 50: colors.surfaceAlt, 100: '#E2E8F0', 500: colors.info, 700: colors.textPrimary };
    
    let deltaColor = colors.textMuted;
    if (deltaDirection === 'up') deltaColor = colors.success;
    else if (deltaDirection === 'down') deltaColor = colors.danger;

    return (
        <View style={styles.card}>
            <View style={[styles.iconPill, { backgroundColor: accentColor[50] }]}>
                <Icon name={icon} size={20} color={accentColor[500]} />
                <Text style={[styles.label, { color: accentColor[700] }]}>{label}</Text>
            </View>
            <View style={styles.content}>
                <Text style={styles.value} numberOfLines={1} adjustsFontSizeToFit>{value}</Text>
                {delta && (
                    <Text style={[styles.delta, { color: deltaColor }]}>{delta}</Text>
                )}
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    card: {
        backgroundColor: colors.surface,
        borderRadius: radius.xl,
        padding: spacing.lg,
        ...shadow.sm,
        flex: 1,
    },
    iconPill: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: spacing.sm,
        paddingVertical: spacing.xs,
        borderRadius: radius.full,
        alignSelf: 'flex-start',
        marginBottom: spacing.md,
    },
    label: {
        ...typography.label,
        marginLeft: spacing.xs,
    },
    content: {
        marginTop: spacing.xs,
    },
    value: {
        ...typography.display,
        color: colors.textPrimary,
    },
    delta: {
        ...typography.caption,
        marginTop: spacing.xs,
        fontWeight: '600',
    }
});
